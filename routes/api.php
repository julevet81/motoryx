<?php

use App\Http\Controllers\Api\V1\Admin\BranchController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\NotificationController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Cars\CarController;
use App\Http\Controllers\Api\V1\Cars\LookupController;
use App\Http\Controllers\Api\V1\CRM\CustomerController;
use App\Http\Controllers\Api\V1\CRM\LeadController;
use App\Http\Controllers\Api\V1\Sales\QuotationController;
use App\Http\Controllers\Api\V1\Sales\ReservationController;
use App\Http\Controllers\Api\V1\Sales\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|
| All routes require:
|   1.  X-Tenant header (or subdomain) resolved by ResolveTenant middleware
|   2.  Sanctum token auth on protected routes
|   3.  EnsureUserBelongsToTenant guard to prevent cross-tenant access
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware(['resolve.tenant'])->group(function () {

    // -------------------------------------------------------
    // Public — lookup data (cached, no auth needed)
    // -------------------------------------------------------
    Route::prefix('lookups')->group(function () {
        Route::get('brands',                [LookupController::class, 'brands']);
        Route::get('brands/{brand}/models', [LookupController::class, 'modelsByBrand']);
        Route::get('categories',            [LookupController::class, 'categories']);
        Route::get('features',              [LookupController::class, 'features']);
        Route::get('enums',                 [LookupController::class, 'enums']);
    });

    // -------------------------------------------------------
    // Auth — rate-limited (6 attempts / minute)
    // -------------------------------------------------------
    Route::prefix('auth')->middleware('throttle:6,1')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    // -------------------------------------------------------
    // Authenticated routes
    // -------------------------------------------------------
    Route::middleware(['auth:sanctum', 'tenant.user', 'subscription'])->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout',         [AuthController::class, 'logout']);
            Route::get('me',              [AuthController::class, 'me']);
            Route::put('profile',         [AuthController::class, 'updateProfile']);
            Route::put('password',        [AuthController::class, 'changePassword']);
        });

        // Cars
        Route::prefix('cars')->group(function () {
            Route::get('/',                   [CarController::class, 'index']);
            Route::post('/',                  [CarController::class, 'store']);
            Route::get('{id}',                [CarController::class, 'show']);
            Route::post('{id}',               [CarController::class, 'update']);  // POST for multipart/form-data
            Route::delete('{id}',             [CarController::class, 'destroy']);
            Route::post('{id}/publish',       [CarController::class, 'publish']);
            Route::delete('{car}/images/{image}', [CarController::class, 'deleteImage']);
        });

        // CRM — Customers
        Route::apiResource('customers', CustomerController::class)->only(['index','store','show','update','destroy']);

        // CRM — Leads
        Route::prefix('leads')->group(function () {
            Route::get('/',                        [LeadController::class, 'index']);
            Route::post('/',                       [LeadController::class, 'store']);
            Route::get('{id}',                     [LeadController::class, 'show']);
            Route::put('{id}',                     [LeadController::class, 'update']);
            Route::delete('{id}',                  [LeadController::class, 'destroy']);
            Route::post('{id}/activities',         [LeadController::class, 'addActivity']);
        });

        // Sales — Quotations
        Route::prefix('quotations')->group(function () {
            Route::get('/',              [QuotationController::class, 'index']);
            Route::post('/',             [QuotationController::class, 'store']);
            Route::get('{id}',           [QuotationController::class, 'show']);
            Route::patch('{id}/status',  [QuotationController::class, 'updateStatus']);
        });

        // Sales — Sales
        Route::prefix('sales')->group(function () {
            Route::get('/',                    [SaleController::class, 'index']);
            Route::post('/',                   [SaleController::class, 'store']);
            Route::get('{id}',                 [SaleController::class, 'show']);
            Route::patch('{id}/cancel',        [SaleController::class, 'cancel']);
            Route::post('{id}/payments',       [SaleController::class, 'addPayment']);
        });

        // Sales — Reservations
        Route::prefix('reservations')->group(function () {
            Route::get('/',             [ReservationController::class, 'index']);
            Route::post('/',            [ReservationController::class, 'store']);
            Route::get('{id}',          [ReservationController::class, 'show']);
            Route::patch('{id}/status', [ReservationController::class, 'updateStatus']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/',                    [NotificationController::class, 'index']);
            Route::patch('{id}/read',          [NotificationController::class, 'markRead']);
            Route::post('read-all',            [NotificationController::class, 'markAllRead']);
            Route::delete('{id}',              [NotificationController::class, 'destroy']);
        });

        // -------------------------------------------------------
        // Admin-only routes (role: admin)
        // -------------------------------------------------------
        Route::middleware('role:admin,manager')->group(function () {

            // Dashboard KPIs
            Route::get('dashboard', [DashboardController::class, 'index']);

            // Branch management
            Route::apiResource('branches', BranchController::class);

            // User management
            Route::prefix('users')->group(function () {
                Route::get('/',                        [UserController::class, 'index']);
                Route::post('/',                       [UserController::class, 'store']);
                Route::get('{id}',                     [UserController::class, 'show']);
                Route::put('{id}',                     [UserController::class, 'update']);
                Route::delete('{id}',                  [UserController::class, 'destroy']);
                Route::patch('{id}/reset-password',    [UserController::class, 'resetPassword']);
            });
        });
    });
});

// -------------------------------------------------------
// Fallback
// -------------------------------------------------------
Route::fallback(fn() => response()->json([
    'success' => false,
    'message' => 'API endpoint not found.',
], 404));