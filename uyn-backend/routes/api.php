<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OperationLogController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GarmentModelController;
use App\Http\Controllers\Api\ProductionOrderController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\GarmentCutController;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/login', [AuthController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login');
            });

        Route::middleware([
            'auth:sanctum',
            'active.user',
        ])->group(function () {
            Route::prefix('auth')
                ->name('auth.')
                ->group(function () {
                    Route::get('/me', [AuthController::class, 'me'])
                        ->name('me');

                    Route::post('/logout', [AuthController::class, 'logout'])
                        ->name('logout');
                });

            Route::get('/operation-logs', [OperationLogController::class, 'index'])
                ->middleware('permission:operation-logs.view')
                ->name('operation-logs.index');

            Route::get('/roles', [RoleController::class, 'index'])
                ->middleware('permission:roles.view')
                ->name('roles.index');

            Route::get('/permissions', [PermissionController::class, 'index'])
                ->middleware('permission:roles.view')
                ->name('permissions.index');

            Route::post('/users/{user}/deactivate', [
                UserController::class,
                'deactivate',
            ])->name('users.deactivate');

            Route::post('/users/{user}/activate', [
                UserController::class,
                'activate',
            ])->name('users.activate');

            Route::get('/users', [UserController::class, 'index'])
                ->middleware('permission:users.view')
                ->name('users.index');

            Route::apiResource('users', UserController::class)
                ->only([
                    'store',
                    'show',
                    'update',
                ]);

            Route::get('/areas', [AreaController::class, 'index'])
                ->middleware('permission:employees.view')
                ->name('areas.index');

            Route::get('/employees', [EmployeeController::class, 'index'])
                ->middleware('permission:employees.view')
                ->name('employees.index');

            Route::post('/employees', [EmployeeController::class, 'store'])
                ->middleware('permission:employees.create')
                ->name('employees.store');

            Route::post('/employees/{employee}/deactivate', [
                EmployeeController::class,
                'deactivate',
            ])
                ->middleware('permission:employees.deactivate')
                ->name('employees.deactivate');

            Route::post('/employees/{employee}/activate', [
                EmployeeController::class,
                'activate',
            ])
                ->middleware('permission:employees.activate')
                ->name('employees.activate');

            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
                ->middleware('permission:employees.view')
                ->name('employees.show');

            Route::match(['put', 'patch'], '/employees/{employee}', [
                EmployeeController::class,
                'update',
            ])
                ->middleware('permission:employees.update')
                ->name('employees.update');

            // Rutas para Garment-Models
            Route::get('/garment-models', [GarmentModelController::class, 'index'])
                ->middleware('permission:garment-models.view')
                ->name('garment-models.index');

            Route::post('/garment-models', [GarmentModelController::class, 'store'])
                ->middleware('permission:garment-models.create')
                ->name('garment-models.store');

            Route::post('/garment-models/{garment_model}/deactivate', [
                GarmentModelController::class,
                'deactivate',
            ])
                ->middleware('permission:garment-models.deactivate')
                ->name('garment-models.deactivate');

            Route::post('/garment-models/{garment_model}/activate', [
                GarmentModelController::class,
                'activate',
            ])
                ->middleware('permission:garment-models.activate')
                ->name('garment-models.activate');

            Route::get('/garment-models/{garment_model}', [
                GarmentModelController::class,
                'show',
            ])
                ->middleware('permission:garment-models.view')
                ->name('garment-models.show');

            Route::match(['put', 'patch'], '/garment-models/{garment_model}', [
                GarmentModelController::class,
                'update',
            ])
                ->middleware('permission:garment-models.update')
                ->name('garment-models.update');

            // Rutas para las ordenes de producción y las tallas
            Route::get('/sizes', [SizeController::class, 'index'])
                ->middleware('permission:cuts.view')
                ->name('sizes.index');

            Route::get('/production-orders', [
                ProductionOrderController::class,
                'index',
            ])
                ->middleware('permission:cuts.view')
                ->name('production-orders.index');

            Route::post('/production-orders', [
                ProductionOrderController::class,
                'store',
            ])
                ->middleware('permission:cuts.create')
                ->name('production-orders.store');

            Route::get('/production-orders/{production_order}', [
                ProductionOrderController::class,
                'show',
            ])
                ->middleware('permission:cuts.view')
                ->name('production-orders.show');

            Route::match(['put', 'patch'], '/production-orders/{production_order}', [
                ProductionOrderController::class,
                'update',
            ])
                ->middleware('permission:cuts.update')
                ->name('production-orders.update');

            // Rutas para GarmentCut 
            Route::get('/garment-cuts', [GarmentCutController::class, 'index'])
                ->middleware('permission:cuts.view')
                ->name('garment-cuts.index');

            Route::post('/garment-cuts', [GarmentCutController::class, 'store'])
                ->middleware('permission:cuts.create')
                ->name('garment-cuts.store');

            Route::get('/garment-cuts/{garment_cut}', [
                GarmentCutController::class,
                'show',
            ])
                ->middleware('permission:cuts.view')
                ->name('garment-cuts.show');

            Route::match(['put', 'patch'], '/garment-cuts/{garment_cut}', [
                GarmentCutController::class,
                'update',
            ])
                ->middleware('permission:cuts.update')
                ->name('garment-cuts.update');
        });
    });