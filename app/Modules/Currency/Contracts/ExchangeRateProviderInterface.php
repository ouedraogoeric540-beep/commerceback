<?php

namespace App\Modules\Currency\Contracts;

interface ExchangeRateProviderInterface
{
    /**
     * Récupère le taux de change entre deux devises.
     * Doit lever une exception si le fournisseur est inaccessible ou échoue.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     * @throws \Exception
     */
    public function getRate(string $fromCurrency, string $toCurrency): float;

    /**
     * Récupère les taux de change pour une devise de base vers plusieurs autres.
     * 
     * @param string $baseCurrency
     * @param array $targetCurrencies
     * @return array Associative array [currencyCode => rate]
     * @throws \Exception
     */
    public function getRates(string $baseCurrency, array $targetCurrencies = []): array;
    
    /**
     * Retourne le nom du fournisseur pour historisation.
     */
    public function getName(): string;
}
