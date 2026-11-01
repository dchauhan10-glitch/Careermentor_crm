<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

// Versioned so the format can change later without breaking connected websites.
Route::prefix('v1')->group(function () {
    Route::post('/leads', LeadController::class)->middleware('website.key')->name('api.v1.leads');
});
