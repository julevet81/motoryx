<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponser;

    /**
     * GET /api/v1/admin/dashboard
     * Returns KPIs, recent sales, top salespersons, stock summary.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $tenantId = $tenant->id;

        $period = $request->input('period', 'month'); // week, month, year
        [$from, $to] = $this->periodDates($period);

        // Run all queries concurrently via separate DB calls (no N+1)
        $salesKpi       = $this->salesKpi($tenantId, $from, $to);
        $stockSummary   = $this->stockSummary($tenantId);
        $leadsSummary   = $this->leadsSummary($tenantId, $from, $to);
        $topSalespersons = $this->topSalespersons($tenantId, $from, $to);
        $recentSales    = $this->recentSales($tenantId);
        $monthlySales   = $this->monthlySalesTrend($tenantId);

        return $this->successResponse([
            'period'          => $period,
            'from'            => $from,
            'to'              => $to,
            'sales'           => $salesKpi,
            'stock'           => $stockSummary,
            'leads'           => $leadsSummary,
            'top_salespersons' => $topSalespersons,
            'recent_sales'    => $recentSales,
            'monthly_trend'   => $monthlySales,
        ]);
    }

    // -------------------------------------------------------
    // Private KPI helpers — each is ONE query
    // -------------------------------------------------------

    private function salesKpi(int $tenantId, string $from, string $to): array
    {
        $row = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total_count,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) as completed_revenue,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
            ")
            ->first();

        return [
            'total_count'        => (int) $row->total_count,
            'total_revenue'      => (float) $row->total_revenue,
            'completed_revenue'  => (float) $row->completed_revenue,
            'completed_count'    => (int) $row->completed_count,
            'pending_count'      => (int) $row->pending_count,
            'cancelled_count'    => (int) $row->cancelled_count,
        ];
    }

    private function stockSummary(int $tenantId): array
    {
        $rows = DB::table('cars')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->get()
            ->pluck('cnt', 'status');

        return [
            'total'     => (int) $rows->sum(),
            'available' => (int) ($rows['available'] ?? 0),
            'reserved'  => (int) ($rows['reserved'] ?? 0),
            'sold'      => (int) ($rows['sold'] ?? 0),
            'inactive'  => (int) ($rows['inactive'] ?? 0),
        ];
    }

    private function leadsSummary(int $tenantId, string $from, string $to): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNull('deleted_at')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->get()
            ->pluck('cnt', 'status');

        return [
            'total'       => (int) $rows->sum(),
            'new'         => (int) ($rows['new'] ?? 0),
            'contacted'   => (int) ($rows['contacted'] ?? 0),
            'qualified'   => (int) ($rows['qualified'] ?? 0),
            'won'         => (int) ($rows['won'] ?? 0),
            'lost'        => (int) ($rows['lost'] ?? 0),
        ];
    }

    private function topSalespersons(int $tenantId, string $from, string $to): array
    {
        return DB::table('sales')
            ->join('users', 'sales.salesperson_id', '=', 'users.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$from, $to])
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->selectRaw("
                users.id,
                CONCAT(users.first_name, ' ', users.last_name) as name,
                COUNT(sales.id) as sales_count,
                COALESCE(SUM(sales.total), 0) as total_revenue
            ")
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'sales_count'   => (int) $r->sales_count,
                'total_revenue' => (float) $r->total_revenue,
            ])
            ->toArray();
    }

    private function recentSales(int $tenantId): array
    {
        return DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->join('cars', 'sales.car_id', '=', 'cars.id')
            ->join('brands', 'cars.brand_id', '=', 'brands.id')
            ->join('car_models', 'cars.model_id', '=', 'car_models.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->selectRaw("
                sales.id,
                sales.sale_number,
                sales.sale_date,
                sales.total,
                sales.status,
                CONCAT(customers.first_name, ' ', customers.last_name) as customer_name,
                CONCAT(brands.name, ' ', car_models.name, ' ', cars.year) as car_name
            ")
            ->orderByDesc('sales.created_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'sale_number'   => $r->sale_number,
                'sale_date'     => $r->sale_date,
                'total'         => (float) $r->total,
                'status'        => $r->status,
                'customer_name' => $r->customer_name,
                'car_name'      => $r->car_name,
            ])
            ->toArray();
    }

    private function monthlySalesTrend(int $tenantId): array
    {
        return DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('sale_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->selectRaw("
                DATE_FORMAT(sale_date, '%Y-%m') as month,
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as revenue
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($r) => [
                'month'   => $r->month,
                'count'   => (int) $r->count,
                'revenue' => (float) $r->revenue,
            ])
            ->toArray();
    }

    private function periodDates(string $period): array
    {
        return match ($period) {
            'week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }
}
