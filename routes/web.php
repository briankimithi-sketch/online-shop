<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes serve the Vue SPA for all frontend routes.
| API routes are handled in routes/api.php
|
*/

// Serve Vue app for all frontend routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
