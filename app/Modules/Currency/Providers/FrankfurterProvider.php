<?php

namespace App\Modules\Currency\Providers;

use App\Modules\Currency\Contracts\ExchangeRateProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;
use RuntimeException;

class FrankfurterProvider implements ExchangeRateProviderInterface
{
    private string $baseUrl = 'https://api.frankfurter.app';

    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // XOF n'est pas supporté par Frankfurter, mais il est arrimé à l'Euro (1 EUR = 655.957 XOF)
        $eurToXof = 655.957;
        $xofToEur = 1 / $eurToXof;

        if ($fromCurrency === 'XOF' && $toCurrency === 'EUR') return $xofToEur;
        if ($fromCurrency === 'EUR' && $toCurrency === 'XOF') return $eurToXof;

        if ($fromCurrency === 'XOF') {
            $eurToTarget = $this->getRate('EUR', $toCurrency);
            return $xofToEur * $eurToTarget;
        }

        if ($toCurrency === 'XOF') {
            $sourceToEur = $this->getRate($fromCurrency, 'EUR');
            return $sourceToEur * $eurToXof;
        }

        $response = Http::timeout(3)
            ->retry(2, 100)
            ->get("{$this->baseUrl}/latest", [
                'from' => $fromCurrency,
                'to' => $toCurrency
            ]);

        if ($response->failed()) {
            throw new RuntimeException("FrankfurterProvider failed to fetch rate from {$fromCurrency} to {$toCurrency}");
        }

        $data = $response->json();
        
        if (!isset($data['rates'][$toCurrency])) {
            throw new RuntimeException("Rate not found in Frankfurter response for {$toCurrency}");
        }

        return (float) $data['rates'][$toCurrency];
    }

    public function getRates(string $baseCurrency, array $targetCurrencies = []): array
    {
        $params = ['from' => $baseCurrency];
        if (!empty($targetCurrencies)) {
            $params['to'] = implode(',', $targetCurrencies);
        }

        $response = Http::timeout(5)
            ->retry(2, 100)
            ->get("{$this->baseUrl}/latest", $params);

        if ($response->failed()) {
            throw new RuntimeException("FrankfurterProvider failed to fetch rates for base {$baseCurrency}");
        }

        return $response->json()['rates'] ?? [];
    }

    public function getName(): string
    {
        return 'Frankfurter';
    }
}
