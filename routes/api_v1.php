<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CurrencyController;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
*/

Route::prefix('currencies')->group(function () {
    Route::get('/rates', [CurrencyController::class, 'getRates']);
});
