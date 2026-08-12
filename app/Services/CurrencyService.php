<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Default base currency (reference).
     */
    protected string $baseCurrency = 'XOF';

    /**
     * List of supported currencies in the application.
     */
    protected array $supportedCurrencies = ['XOF', 'EUR', 'USD'];

    /**
     * Get cached exchange rates relative to the base currency.
     */
    public function getRates(): array
    {
        return Cache::remember('exchange_rates_' . $this->baseCurrency, 86400, function () {
            return $this->fetchRatesFromApi();
        });
    }

    /**
     * Fetch rates from ExchangeRate-API.
     */
    protected function fetchRatesFromApi(): array
    {
        $apiKey = config('services.exchangerate.key');
        $baseUrl = config('services.exchangerate.url');

        if (empty($apiKey)) {
            Log::error('ExchangeRate-API key is missing.');
            return [$this->baseCurrency => 1.0];
        }

        try {
            $response = Http::withoutVerifying()->timeout(5)->get("{$baseUrl}/{$apiKey}/latest/{$this->baseCurrency}");

            if ($response->successful() && $response->json('result') === 'success') {
                $conversionRates = $response->json('conversion_rates');
                $rates = [];
                
                foreach ($this->supportedCurrencies as $currency) {
                    if (isset($conversionRates[$currency])) {
                        $rates[$currency] = $conversionRates[$currency];
                    }
                }
                
                // Ensure base currency is 1
                $rates[$this->baseCurrency] = 1.0;
                return $rates;
            }

            Log::error('ExchangeRate-API failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('ExchangeRate-API exception', ['message' => $e->getMessage()]);
        }

        return [$this->baseCurrency => 1.0];
    }
}
