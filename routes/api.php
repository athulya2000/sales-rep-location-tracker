<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\LocationBatchController;
use App\Http\Controllers\Api\V1\StopReportController;
use App\Http\Controllers\Api\V1\ZoneReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/locations/batch', LocationBatchController::class)->name('locations.batch');
    Route::get('/reps/{rep}/stops', StopReportController::class)->name('reps.stops');
    Route::get('/zones/reps', ZoneReportController::class)->name('zones.reps');
});
