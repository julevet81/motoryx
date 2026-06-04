<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['brand_id', 'name']);
            $table->index('brand_id');
        });

        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('car_features', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('model_id')->constrained('car_models');
            $table->foreignId('category_id')->nullable()->constrained('vehicle_categories')->nullOnDelete();

            $table->string('vin', 17)->unique()->nullable();
            $table->string('registration_number')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 50)->nullable();

            $table->string('fuel_type', 30)->nullable();    // petrol, diesel, electric, hybrid
            $table->string('transmission', 30)->nullable(); // manual, automatic, cvt

            $table->unsignedInteger('mileage')->default(0);
            $table->decimal('engine_size', 4, 1)->nullable();
            $table->unsignedSmallInteger('horsepower')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();

            $table->string('condition', 20)->default('used'); // new, used, certified

            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('discounted_price', 12, 2)->nullable();

            $table->text('description')->nullable();
            $table->string('status', 20)->default('available'); // available, reserved, sold, inactive
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'brand_id']);
            $table->index(['tenant_id', 'published_at']);
            $table->index('vin');
        });

        Schema::create('car_feature_car', function (Blueprint $table) {
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('car_features')->cascadeOnDelete();
            $table->primary(['car_id', 'feature_id']);
        });

        Schema::create('car_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->index(['car_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_images');
        Schema::dropIfExists('car_feature_car');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('car_features');
        Schema::dropIfExists('vehicle_categories');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('brands');
    }
};
