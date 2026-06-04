<?php

namespace App\Http\Requests\Cars;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $currentYear = date('Y');
        $carId = $this->route('car') ?? $this->route('id');

        return [
            'branch_id'           => 'sometimes|integer',
            'brand_id'            => 'sometimes|integer|exists:brands,id',
            'model_id'            => 'sometimes|integer|exists:car_models,id',
            'category_id'         => 'sometimes|nullable|integer|exists:vehicle_categories,id',
            'vin'                 => ['sometimes', 'nullable', 'string', 'size:17', Rule::unique('cars', 'vin')->ignore($carId)],
            'registration_number' => 'sometimes|nullable|string|max:50',
            'year'                => "sometimes|nullable|integer|min:1950|max:{$currentYear}",
            'color'               => 'sometimes|nullable|string|max:50',
            'fuel_type'           => 'sometimes|nullable|string|in:petrol,diesel,electric,hybrid',
            'transmission'        => 'sometimes|nullable|string|in:manual,automatic,cvt',
            'mileage'             => 'sometimes|nullable|integer|min:0|max:9999999',
            'engine_size'         => 'sometimes|nullable|numeric|min:0|max:20',
            'horsepower'          => 'sometimes|nullable|integer|min:0|max:5000',
            'seats'               => 'sometimes|nullable|integer|min:1|max:50',
            'doors'               => 'sometimes|nullable|integer|min:1|max:10',
            'condition'           => 'sometimes|nullable|string|in:new,used,certified',
            'purchase_price'      => 'sometimes|nullable|numeric|min:0',
            'selling_price'       => 'sometimes|nullable|numeric|min:0',
            'discounted_price'    => 'sometimes|nullable|numeric|min:0',
            'description'         => 'sometimes|nullable|string|max:5000',
            'status'              => 'sometimes|nullable|string|in:available,reserved,sold,inactive',
            'published_at'        => 'sometimes|nullable|date',
            'feature_ids'         => 'sometimes|nullable|array',
            'feature_ids.*'       => 'integer|exists:car_features,id',
            'images'              => 'sometimes|nullable|array|max:10',
            'images.*'            => 'image|mimes:jpeg,png,webp|max:4096',
        ];
    }
}
