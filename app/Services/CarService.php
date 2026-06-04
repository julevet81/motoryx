<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarImage;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarService
{
    public function paginate(Tenant $tenant, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Car::query()
            ->forTenant($tenant->id)
            ->with(['brand', 'model', 'category', 'images' => fn($q) => $q->limit(1)])
            ->filter($filters)
            ->latest()
            ->paginate(min($perPage, 50)); // Cap at 50 per page
    }

    public function create(Tenant $tenant, array $data, array $imageFiles = []): Car
    {
        return DB::transaction(function () use ($tenant, $data, $imageFiles) {
            $data['tenant_id'] = $tenant->id;

            $car = Car::create($data);

            if (isset($data['feature_ids'])) {
                $car->features()->sync($data['feature_ids']);
            }

            foreach ($imageFiles as $index => $file) {
                $this->storeImage($car, $file, $index);
            }

            return $car->load(['brand', 'model', 'category', 'features', 'images']);
        });
    }

    public function update(Car $car, array $data, array $imageFiles = []): Car
    {
        return DB::transaction(function () use ($car, $data, $imageFiles) {
            $car->update($data);

            if (array_key_exists('feature_ids', $data)) {
                $car->features()->sync($data['feature_ids'] ?? []);
            }

            foreach ($imageFiles as $index => $file) {
                $this->storeImage($car, $file, $car->images()->max('sort_order') + 1 + $index);
            }

            return $car->fresh(['brand', 'model', 'category', 'features', 'images']);
        });
    }

    public function delete(Car $car): void
    {
        DB::transaction(function () use ($car) {
            // Delete stored images from disk
            $car->images->each(fn($img) => Storage::disk('public')->delete($img->image));
            $car->delete(); // Soft delete
        });
    }

    public function deleteImage(CarImage $image): void
    {
        Storage::disk('public')->delete($image->image);
        $image->delete();
    }

    private function storeImage(Car $car, UploadedFile $file, int $sortOrder): CarImage
    {
        $path = $file->store("cars/{$car->tenant_id}/{$car->id}", 'public');

        return CarImage::create([
            'car_id'     => $car->id,
            'image'      => $path,
            'sort_order' => $sortOrder,
        ]);
    }
}
