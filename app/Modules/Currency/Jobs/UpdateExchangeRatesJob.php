<?php

namespace App\Modules\Currency\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Currency\Contracts\ExchangeRateProviderInterface;
use App\Modules\Currency\Models\Currency;
use App\Modules\Currency\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes max

    public function handle()
    {
        $pivot = config('currency.pivot_currency', 'USD');
        $activeCurrencies = Currency::where('is_active', true)
            ->where('code', '!=', $pivot)
            ->pluck('code')
            ->toArray();

        if (empty($activeCurrencies)) {
            Log::info("No active currencies to sync.");
            return;
        }

        // Stratégie Fallback (Chain of responsibility)
        $providers = [
            config('currency.providers.primary'),
            config('currency.providers.secondary')
        ];

        $rates = [];
        $providerUsed = 'Unknown';

        foreach ($providers as $providerClass) {
            if ($providerClass && class_exists($providerClass)) {
                /** @var ExchangeRateProviderInterface $provider */
                $provider = new $providerClass();
                try {
                    $rates = $provider->getRates($pivot, $activeCurrencies);
                    $providerUsed = $provider->getName();
                    break; // Succès, on sort de la boucle
                } catch (\Exception $e) {
                    Log::warning("Currency Provider [{$provider->getName()}] failed during sync: " . $e->getMessage());
                }
            }
        }

        if (empty($rates)) {
            Log::error("CRITICAL: All external currency providers failed during sync.");
            return; // On conserve les anciens taux en BDD/Cache
        }

        // Sauvegarde en Base
        foreach ($rates as $currencyCode => $rateValue) {
            // Clôture l'ancien taux
            ExchangeRate::where('from_currency', $pivot)
                ->where('to_currency', $currencyCode)
                ->where('status', 'active')
                ->update([
                    'status' => 'historical',
                    'effective_until' => now()
                ]);

            // Insère le nouveau
            ExchangeRate::create([
                'from_currency' => $pivot,
                'to_currency' => $currencyCode,
                'rate' => $rateValue,
                'provider_used' => $providerUsed,
                'effective_from' => now(),
                'status' => 'active'
            ]);
        }

        // Invalider le Cache Redis pour forcer le rechargement
        $cachePrefix = config('currency.cache.prefix', 'currency_rate_');
        $cacheStore = config('currency.cache.store', 'redis');
        foreach ($activeCurrencies as $currencyCode) {
            Cache::store($cacheStore)->forget($cachePrefix . $pivot . '_' . $currencyCode);
            Cache::store($cacheStore)->forget($cachePrefix . $currencyCode . '_' . $pivot);
        }

        Log::info("Currency Exchange Rates synced successfully using {$providerUsed}.");
    }
}
