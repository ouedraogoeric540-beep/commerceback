<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Services\CurrencyService;

class CurrencyController extends Controller
{
    /**
     * Get the latest exchange rates.
     */
    public function getRates(CurrencyService $currencyService): JsonResponse
    {
        $rates = $currencyService->getRates();
        
        return response()->json([
            'base' => 'XOF',
            'rates' => $rates
        ]);
    }
}
