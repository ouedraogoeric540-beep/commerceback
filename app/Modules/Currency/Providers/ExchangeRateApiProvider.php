<?php

namespace App\Modules\Currency\Providers;

use App\Modules\Currency\Contracts\ExchangeRateProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateApiProvider implements ExchangeRateProviderInterface
{
    private string $baseUrl = 'https://v6.exchangerate-api.com/v6';

    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $apiKey = config('currency.api_keys.exchange_rate_api');
        if (empty($apiKey)) {
            throw new RuntimeException("ExchangeRateApiProvider requires an API key.");
        }

        $response = Http::timeout(3)
            ->retry(2, 100)
            ->get("{$this->baseUrl}/{$apiKey}/pair/{$fromCurrency}/{$toCurrency}");

        if ($response->failed()) {
            throw new RuntimeException("ExchangeRateApiProvider failed to fetch rate from {$fromCurrency} to {$toCurrency}");
        }

        $data = $response->json();
        
        if ($data['result'] !== 'success') {
            throw new RuntimeException("Rate not found in ExchangeRateAPI response.");
        }

        return (float) $data['conversion_rate'];
    }

    public function getRates(string $baseCurrency, array $targetCurrencies = []): array
    {
        $apiKey = config('currency.api_keys.exchange_rate_api');
        if (empty($apiKey)) {
            throw new RuntimeException("ExchangeRateApiProvider requires an API key.");
        }

        $response = Http::timeout(5)
            ->retry(2, 100)
            ->get("{$this->baseUrl}/{$apiKey}/latest/{$baseCurrency}");

        if ($response->failed()) {
            throw new RuntimeException("ExchangeRateApiProvider failed to fetch rates for base {$baseCurrency}");
        }

        $data = $response->json();
        if ($data['result'] !== 'success') {
            throw new RuntimeException("Failed to fetch latest rates from ExchangeRateAPI.");
        }

        $rates = $data['conversion_rates'] ?? [];
        
        if (!empty($targetCurrencies)) {
            return array_intersect_key($rates, array_flip($targetCurrencies));
        }

        return $rates;
    }

    public function getName(): string
    {
        return 'ExchangeRateAPI';
    }
}
