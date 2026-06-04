<?php

namespace App\Http\Controllers\Api\V1\Cars;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\StoreCarRequest;
use App\Http\Requests\Cars\UpdateCarRequest;
use App\Models\Car;
use App\Models\CarImage;
use App\Services\CarService;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarController extends Controller
{
    use ApiResponser, LogsActivity;

    public function __construct(private readonly CarService $carService) {}

    /**
     * GET /api/v1/cars
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page'     => 'integer|min:1|max:50',
            'brand_id'     => 'integer|exists:brands,id',
            'model_id'     => 'integer|exists:car_models,id',
            'category_id'  => 'integer|exists:vehicle_categories,id',
            'year_from'    => 'integer|min:1950|max:' . (date('Y') + 1),
            'year_to'      => 'integer|min:1950|max:' . (date('Y') + 1),
            'price_from'   => 'numeric|min:0',
            'price_to'     => 'numeric|min:0',
            'fuel_type'    => 'string|in:petrol,diesel,electric,hybrid',
            'transmission' => 'string|in:manual,automatic,cvt',
            'condition'    => 'string|in:new,used,certified',
            'status'       => 'string|in:available,reserved,sold,inactive',
            'search'       => 'string|max:100',
        ]);

        $tenant    = app('tenant');
        $paginator = $this->carService->paginate($tenant, $request->only([
            'brand_id', 'model_id', 'category_id', 'year_from', 'year_to',
            'price_from', 'price_to', 'fuel_type', 'transmission', 'condition',
            'status', 'search',
        ]), (int) $request->input('per_page', 15));

        return $this->paginatedResponse($paginator, fn($car) => $this->carResource($car));
    }

    /**
     * POST /api/v1/cars
     */
    public function store(StoreCarRequest $request): JsonResponse
    {
        $tenant = app('tenant');

        if (! $tenant->withinLimit('cars')) {
            return $this->errorResponse('You have reached the cars limit for your plan.', 403);
        }

        $car = $this->carService->create(
            $tenant,
            $request->validated(),
            $request->file('images', [])
        );

        $this->logActivity('car.created', $car);

        return $this->createdResponse($this->carResource($car), 'Car created successfully.');
    }

    /**
     * GET /api/v1/cars/{car}
     */
    public function show(int $id): JsonResponse
    {
        $car = Car::forTenant(app('tenant')->id)
            ->with(['brand', 'model', 'category', 'features', 'images', 'branch'])
            ->findOrFail($id);

        return $this->successResponse($this->carResource($car, detailed: true));
    }

    /**
     * PUT /api/v1/cars/{car}
     */
    public function update(UpdateCarRequest $request, int $id): JsonResponse
    {
        $car = Car::forTenant(app('tenant')->id)->findOrFail($id);
        $car = $this->carService->update($car, $request->validated(), $request->file('images', []));

        $this->logActivity('car.updated', $car);

        return $this->successResponse($this->carResource($car), 'Car updated successfully.');
    }

    /**
     * DELETE /api/v1/cars/{car}
     */
    public function destroy(int $id): JsonResponse
    {
        $car = Car::forTenant(app('tenant')->id)->findOrFail($id);

        if ($car->status === 'sold') {
            return $this->errorResponse('Cannot delete a sold car.', 422);
        }

        $this->carService->delete($car);
        $this->logActivity('car.deleted', $car, ['vin' => $car->vin]);

        return $this->noContentResponse();
    }

    /**
     * DELETE /api/v1/cars/{car}/images/{image}
     */
    public function deleteImage(int $carId, int $imageId): JsonResponse
    {
        $car   = Car::forTenant(app('tenant')->id)->findOrFail($carId);
        $image = CarImage::where('car_id', $car->id)->findOrFail($imageId);

        $this->carService->deleteImage($image);

        return $this->noContentResponse();
    }

    /**
     * POST /api/v1/cars/{car}/publish
     */
    public function publish(int $id): JsonResponse
    {
        $car = Car::forTenant(app('tenant')->id)->findOrFail($id);
        $car->update(['published_at' => now(), 'status' => 'available']);

        return $this->successResponse(null, 'Car published.');
    }

    // -------------------------------------------------------
    // Resource transformer
    // -------------------------------------------------------

    private function carResource(Car $car, bool $detailed = false): array
    {
        $data = [
            'id'                  => $car->id,
            'brand'               => $car->relationLoaded('brand') ? $car->brand->only('id', 'name') : null,
            'model'               => $car->relationLoaded('model') ? $car->model->only('id', 'name') : null,
            'category'            => $car->relationLoaded('category') ? $car->category?->only('id', 'name') : null,
            'year'                => $car->year,
            'color'               => $car->color,
            'fuel_type'           => $car->fuel_type,
            'transmission'        => $car->transmission,
            'mileage'             => $car->mileage,
            'condition'           => $car->condition,
            'selling_price'       => $car->selling_price,
            'discounted_price'    => $car->discounted_price,
            'effective_price'     => $car->effective_price,
            'status'              => $car->status,
            'published_at'        => $car->published_at?->toIso8601String(),
            'primary_image'       => $this->imageUrl($car->images->first()),
            'created_at'          => $car->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'vin'                => $car->vin,
                'registration_number' => $car->registration_number,
                'engine_size'        => $car->engine_size,
                'horsepower'         => $car->horsepower,
                'seats'              => $car->seats,
                'doors'              => $car->doors,
                'purchase_price'     => $car->purchase_price,
                'description'        => $car->description,
                'features'           => $car->relationLoaded('features')
                    ? $car->features->map->only('id', 'name')->values()
                    : null,
                'images'             => $car->relationLoaded('images')
                    ? $car->images->map(fn($img) => [
                        'id'         => $img->id,
                        'url'        => $this->imageUrl($img),
                        'sort_order' => $img->sort_order,
                    ])->values()
                    : null,
                'branch'             => $car->relationLoaded('branch')
                    ? $car->branch->only('id', 'name', 'city')
                    : null,
            ]);
        }

        return $data;
    }

    private function imageUrl(?CarImage $image): ?string
    {
        return $image ? asset('storage/' . $image->image) : null;
    }
}
