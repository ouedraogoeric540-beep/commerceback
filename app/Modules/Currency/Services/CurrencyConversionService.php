<?php

namespace App\Modules\Currency\Services;

use App\Modules\Currency\Contracts\ExchangeRateProviderInterface;
use App\Modules\Currency\ValueObjects\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class CurrencyConversionService
{
    /** @var ExchangeRateProviderInterface[] */
    private array $providers = [];
    private string $pivotCurrency;
    private string $cachePrefix;
    private int $cacheTtl;

    public function __construct()
    {
        $this->pivotCurrency = config('currency.pivot_currency', 'USD');
        $this->cachePrefix = config('currency.cache.prefix', 'currency_rate_');
        $this->cacheTtl = config('currency.cache.ttl', 3600);

        // Instanciation de la chaîne de responsabilité
        $primaryClass = config('currency.providers.primary');
        $fallbackClass = config('currency.providers.fallback');

        if ($primaryClass && class_exists($primaryClass)) {
            $this->providers[] = new $primaryClass();
        }
        
        if ($fallbackClass && class_exists($fallbackClass)) {
            $this->providers[] = new $fallbackClass();
        }
    }

    /**
     * Convertit un objet Money vers une nouvelle devise.
     */
    public function convert(Money $money, string $targetCurrency): Money
    {
        $targetCurrency = strtoupper($targetCurrency);
        $sourceCurrency = $money->getCurrency();

        if ($sourceCurrency === $targetCurrency) {
            return clone $money;
        }

        // Cas 1 : Convertir vers Pivot
        if ($targetCurrency === $this->pivotCurrency) {
            $rate = $this->getRate($sourceCurrency, $this->pivotCurrency);
            $newAmount = $money->multiply($rate)->getAmount();
            return new Money($newAmount, $targetCurrency);
        }

        // Cas 2 : Convertir depuis Pivot
        if ($sourceCurrency === $this->pivotCurrency) {
            $rate = $this->getRate($this->pivotCurrency, $targetCurrency);
            $newAmount = $money->multiply($rate)->getAmount();
            return new Money($newAmount, $targetCurrency);
        }

        // Cas 3 : Cross-currency (Source -> Pivot -> Target)
        $rateToPivot = $this->getRate($sourceCurrency, $this->pivotCurrency);
        $amountInPivot = $money->multiply($rateToPivot);
        
        $rateFromPivot = $this->getRate($this->pivotCurrency, $targetCurrency);
        $finalAmount = $amountInPivot->multiply($rateFromPivot)->getAmount();
        return new Money($finalAmount, $targetCurrency);
    }

    /**
     * Récupère le taux (via le Cache ou l'API).
     */
    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = $this->cachePrefix . $fromCurrency . '_' . $toCurrency;

        return Cache::store(config('currency.cache.store', 'redis'))->remember($cacheKey, $this->cacheTtl, function () use ($fromCurrency, $toCurrency) {
            return $this->fetchRateFromProviders($fromCurrency, $toCurrency);
        });
    }

    /**
     * Chain of responsibility sur les providers.
     */
    private function fetchRateFromProviders(string $from, string $to): float
    {
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                return $provider->getRate($from, $to);
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("Currency Provider [{$provider->getName()}] failed for {$from}->{$to}: " . $e->getMessage());
                // On passe au suivant
            }
        }

        Log::error("All Currency Providers failed for {$from}->{$to}.");
        throw new Exception("Unable to fetch exchange rate for {$from} to {$to}. Last error: " . ($lastException ? $lastException->getMessage() : ''));
    }
}
