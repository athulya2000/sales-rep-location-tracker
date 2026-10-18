<?php

declare(strict_types=1);

use App\Http\Controllers\Web\RepDayViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('reps.day', ['rep' => 1]);
});

Route::get('/reps/{rep}/day', RepDayViewController::class)->name('reps.day');
