<?php

namespace App\Http\Controllers\Api\V1\Cars;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarFeature;
use App\Models\VehicleCategory;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    use ApiResponser;

    /**
     * GET /api/v1/lookups/brands
     * Returns all brands with their models.
     */
    public function brands(): JsonResponse
    {
        $brands = Cache::remember('lookups:brands', 3600, fn() =>
            Brand::with('carModels:id,brand_id,name')
                ->orderBy('name')
                ->get()
                ->map(fn($b) => [
                    'id'     => $b->id,
                    'name'   => $b->name,
                    'logo'   => $b->logo ? asset('storage/' . $b->logo) : null,
                    'models' => $b->carModels->map->only('id', 'name')->values(),
                ])
        );

        return $this->successResponse($brands);
    }

    /**
     * GET /api/v1/lookups/brands/{brand}/models
     */
    public function modelsByBrand(int $brandId): JsonResponse
    {
        $models = Cache::remember("lookups:models:{$brandId}", 3600, fn() =>
            \App\Models\CarModel::where('brand_id', $brandId)
                ->orderBy('name')
                ->get(['id', 'name'])
        );

        return $this->successResponse($models);
    }

    /**
     * GET /api/v1/lookups/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Cache::remember('lookups:categories', 3600, fn() =>
            VehicleCategory::orderBy('name')->get(['id', 'name'])
        );

        return $this->successResponse($categories);
    }

    /**
     * GET /api/v1/lookups/features
     */
    public function features(): JsonResponse
    {
        $features = Cache::remember('lookups:features', 3600, fn() =>
            CarFeature::orderBy('name')->get(['id', 'name'])
        );

        return $this->successResponse($features);
    }

    /**
     * GET /api/v1/lookups/enums
     * Returns all enum values used in forms.
     */
    public function enums(): JsonResponse
    {
        return $this->successResponse([
            'fuel_types'       => ['petrol', 'diesel', 'electric', 'hybrid'],
            'transmissions'    => ['manual', 'automatic', 'cvt'],
            'conditions'       => ['new', 'used', 'certified'],
            'car_statuses'     => ['available', 'reserved', 'sold', 'inactive'],
            'lead_sources'     => ['website', 'walk_in', 'phone', 'social_media', 'referral', 'other'],
            'lead_statuses'    => ['new', 'contacted', 'qualified', 'negotiating', 'won', 'lost'],
            'lead_activities'  => ['call', 'email', 'meeting', 'note', 'status_change', 'other'],
            'sale_statuses'    => ['pending', 'completed', 'cancelled', 'refunded'],
            'payment_methods'  => ['cash', 'bank_transfer', 'check', 'card'],
            'quotation_statuses' => ['draft', 'sent', 'accepted', 'rejected', 'expired'],
            'reservation_statuses' => ['pending', 'confirmed', 'cancelled', 'completed'],
            'user_roles'       => ['manager', 'agent'],
            'user_statuses'    => ['active', 'inactive', 'suspended'],
        ]);
    }
}
