<?php

namespace App\Http\Requests\Cars;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $currentYear = date('Y');

        return [
            'branch_id'           => 'required|integer',
            'brand_id'            => 'required|integer|exists:brands,id',
            'model_id'            => 'required|integer|exists:car_models,id',
            'category_id'         => 'nullable|integer|exists:vehicle_categories,id',
            'vin'                 => 'nullable|string|size:17|unique:cars,vin',
            'registration_number' => 'nullable|string|max:50',
            'year'                => "nullable|integer|min:1950|max:{$currentYear}",
            'color'               => 'nullable|string|max:50',
            'fuel_type'           => 'nullable|string|in:petrol,diesel,electric,hybrid',
            'transmission'        => 'nullable|string|in:manual,automatic,cvt',
            'mileage'             => 'nullable|integer|min:0|max:9999999',
            'engine_size'         => 'nullable|numeric|min:0|max:20',
            'horsepower'          => 'nullable|integer|min:0|max:5000',
            'seats'               => 'nullable|integer|min:1|max:50',
            'doors'               => 'nullable|integer|min:1|max:10',
            'condition'           => 'nullable|string|in:new,used,certified',
            'purchase_price'      => 'nullable|numeric|min:0',
            'selling_price'       => 'nullable|numeric|min:0',
            'discounted_price'    => 'nullable|numeric|min:0|lt:selling_price',
            'description'         => 'nullable|string|max:5000',
            'status'              => 'nullable|string|in:available,reserved,sold,inactive',
            'published_at'        => 'nullable|date',
            'feature_ids'         => 'nullable|array',
            'feature_ids.*'       => 'integer|exists:car_features,id',
            'images'              => 'nullable|array|max:10',
            'images.*'            => 'image|mimes:jpeg,png,webp|max:4096',
        ];
    }

    public function messages(): array
    {
        return [
            'vin.size'                   => 'VIN must be exactly 17 characters.',
            'discounted_price.lt'        => 'Discounted price must be less than selling price.',
            'images.*.max'               => 'Each image must not exceed 4MB.',
        ];
    }
}
