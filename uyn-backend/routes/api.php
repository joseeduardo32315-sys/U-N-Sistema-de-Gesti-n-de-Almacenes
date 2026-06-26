<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OperationLogController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;

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
        });
    });