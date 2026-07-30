<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Currency\Http\Controllers\CurrencyController;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
*/

Route::prefix('currencies')->group(function () {
    // Endpoints publics
    Route::get('/', [CurrencyController::class, 'index']);
    
    // Endpoints d'administration (A protéger par auth:sanctum + Admin policy plus tard)
    Route::post('/sync', [CurrencyController::class, 'syncRates']);
});
