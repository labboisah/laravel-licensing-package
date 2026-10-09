<?php

use Illuminate\Support\Facades\Route;
use KernelBridge\LicensingClient\Http\Controllers\LicenseController;

Route::middleware(config('kernelbridge-licensing.routes.middleware', ['web']))
    ->prefix(config('kernelbridge-licensing.routes.prefix', 'license'))
    ->name(config('kernelbridge-licensing.routes.name', 'kernelbridge.license.'))
    ->group(function (): void {
        Route::get('/', [LicenseController::class, 'show'])->name('show');
        Route::post('/', [LicenseController::class, 'activate'])->middleware('throttle:5,1')->name('activate');
        Route::post('/verify', [LicenseController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
        Route::delete('/', [LicenseController::class, 'deactivate'])->middleware('throttle:5,1')->name('deactivate');
    });
