<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponser;

    /**
     * GET /api/v1/dashboard
     * Returns KPIs, charts data, and recent activity.
     * Results cached per tenant for 5 minutes.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant  = app('tenant');
        $cacheKey = "dashboard:{$tenant->id}";

        $data = Cache::remember($cacheKey, 300, fn() => $this->buildDashboard($tenant->id));

        return $this->successResponse($data);
    }

    private function buildDashboard(int $tenantId): array
    {
        $now       = now();
        $thisMonth = $now->format('Y-m');
        $lastMonth = $now->copy()->subMonth()->format('Y-m');

        // ── Cars KPIs ──────────────────────────────────────────
        $carStats = Car::forTenant($tenantId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = "sold"      THEN 1 ELSE 0 END) as sold,
                SUM(CASE WHEN status = "reserved"  THEN 1 ELSE 0 END) as reserved
            ')
            ->first();

        // ── Sales KPIs ─────────────────────────────────────────
        $salesThis = Sale::forTenant($tenantId)
            ->whereRaw("DATE_FORMAT(sale_date, '%Y-%m') = ?", [$thisMonth])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $salesLast = Sale::forTenant($tenantId)
            ->whereRaw("DATE_FORMAT(sale_date, '%Y-%m') = ?", [$lastMonth])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        // ── Revenue by month (last 6 months) ──────────────────
        $revenueChart = Sale::forTenant($tenantId)
            ->where('status', '!=', 'cancelled')
            ->where('sale_date', '>=', $now->copy()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(sale_date, '%Y-%m') as month, SUM(total) as revenue, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ── Leads by status ────────────────────────────────────
        $leadStats = Lead::forTenant($tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Top salespersons (this month) ─────────────────────
        $topSales = Sale::forTenant($tenantId)
            ->whereRaw("DATE_FORMAT(sale_date, '%Y-%m') = ?", [$thisMonth])
            ->where('status', '!=', 'cancelled')
            ->with('salesperson:id,first_name,last_name')
            ->selectRaw('salesperson_id, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('salesperson_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'salesperson' => $s->salesperson?->full_name,
                'count'       => $s->count,
                'revenue'     => $s->revenue,
            ]);

        // ── Recent sales ───────────────────────────────────────
        $recentSales = Sale::forTenant($tenantId)
            ->with(['customer:id,first_name,last_name', 'car:id,brand_id,model_id', 'car.brand:id,name', 'car.model:id,name'])
            ->latest('sale_date')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'sale_number' => $s->sale_number,
                'customer'    => $s->customer?->full_name,
                'car'         => $s->car ? "{$s->car->brand?->name} {$s->car->model?->name}" : null,
                'total'       => $s->total,
                'status'      => $s->status,
                'sale_date'   => $s->sale_date->toDateString(),
            ]);

        // ── Revenue growth % ──────────────────────────────────
        $prevRevenue = (float) $salesLast->revenue;
        $curRevenue  = (float) $salesThis->revenue;
        $growth      = $prevRevenue > 0
            ? round((($curRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($curRevenue > 0 ? 100 : 0);

        return [
            'cars' => [
                'total'     => (int) $carStats->total,
                'available' => (int) $carStats->available,
                'sold'      => (int) $carStats->sold,
                'reserved'  => (int) $carStats->reserved,
            ],
            'sales_this_month' => [
                'count'          => (int) $salesThis->count,
                'revenue'        => (float) $salesThis->revenue,
                'revenue_growth' => $growth,
            ],
            'customers_total' => Customer::forTenant($tenantId)->count(),
            'leads'           => $leadStats,
            'revenue_chart'   => $revenueChart->map(fn($r) => [
                'month'   => $r->month,
                'revenue' => (float) $r->revenue,
                'count'   => (int) $r->count,
            ]),
            'top_salespersons' => $topSales,
            'recent_sales'     => $recentSales,
        ];
    }
}
