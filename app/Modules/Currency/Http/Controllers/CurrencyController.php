<?php

namespace App\Modules\Currency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Http\Resources\CurrencyResource;
use App\Modules\Currency\Jobs\UpdateExchangeRatesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CurrencyController extends Controller
{
    /**
     * Liste des devises actives (Statique pour de hautes performances et sans BD).
     */
    public function index(\App\Modules\Currency\Services\CurrencyConversionService $conversionService)
    {
        $currenciesData = Cache::remember('api_currencies_static_rates', 86400, function () use ($conversionService) {
            
            // Liste statique des devises supportées par la marketplace
            $supportedCurrencies = [
                ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO)', 'symbol' => 'CFA', 'decimal_places' => 2],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
                ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2],
                ['code' => 'CNY', 'name' => 'Yuan', 'symbol' => '¥', 'decimal_places' => 2],
            ];

            $pivot = config('currency.pivot_currency', 'XOF');
            
            // Taux de secours approximatifs en cas de panne de l'API externe
            $fallbackRates = [
                'XOF' => 1,
                'USD' => 0.0016,
                'EUR' => 0.0015,
                'GBP' => 0.0013,
                'CNY' => 0.012, // 1 XOF = ~0.012 CNY
            ];
            
            foreach ($supportedCurrencies as &$item) {
                try {
                    $item['exchange_rate'] = $conversionService->getRate($pivot, $item['code']);
                } catch (\Exception $e) {
                    $item['exchange_rate'] = $fallbackRates[$item['code']] ?? 1;
                }
            }
            
            return $supportedCurrencies;
        });

        return response()->json([
            'data' => $currenciesData
        ]);
    }

    /**
     * Déclenche une synchronisation des taux (Cache).
     */
    public function syncRates()
    {
        Cache::forget('api_currencies_static_rates');

        return response()->json([
            'message' => 'Cache cleared. Exchange rates will be refreshed on next request.'
        ]);
    }
}
