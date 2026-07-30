<?php

namespace Tests\Unit\Currency;

use App\Modules\Currency\Services\CurrencyConversionService;
use App\Modules\Currency\ValueObjects\Money;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use InvalidArgumentException;
use Exception;

class CurrencyConversionServiceTest extends TestCase
{
    protected CurrencyConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration de la devise de base (Pivot) et du cache pour les tests
        config(['currency.base_currency' => 'XOF']);
        config(['currency.pivot_currency' => 'XOF']);
        config(['cache.default' => 'array']);
        config(['currency.cache.store' => 'array']);
        config(['currency.providers.fallback' => null]);
        
        // Simuler les taux dans le cache (bypassing providers for test)
        Cache::store('array')->put('currency_rate_XOF_USD', 0.001600, 3600);
        Cache::store('array')->put('currency_rate_USD_XOF', 625, 3600);
        Cache::store('array')->put('currency_rate_XOF_EUR', 0.001500, 3600);
        Cache::store('array')->put('currency_rate_EUR_XOF', 666.6666, 3600);

        $this->service = app(CurrencyConversionService::class);
    }

    public function test_it_converts_from_base_to_target_currency()
    {
        $money = new Money(10000, 'XOF');
        
        $converted = $this->service->convert($money, 'USD');

        $this->assertEquals('16.000000', $converted->getAmount());
        $this->assertEquals('USD', $converted->getCurrency());
    }

    public function test_it_converts_from_target_to_base_currency()
    {
        $money = new Money(16, 'USD');
        
        $converted = $this->service->convert($money, 'XOF');

        $this->assertEquals('10000.000000', $converted->getAmount());
        $this->assertEquals('XOF', $converted->getCurrency());
    }

    public function test_it_converts_between_two_non_base_currencies()
    {
        $money = new Money(16, 'USD'); // 16 USD = 10000 XOF
        
        $converted = $this->service->convert($money, 'EUR');

        $this->assertEquals('15.000000', $converted->getAmount());
        $this->assertEquals('EUR', $converted->getCurrency());
    }

    public function test_it_returns_same_amount_if_currencies_are_identical()
    {
        $money = new Money(500, 'EUR');
        $converted = $this->service->convert($money, 'EUR');

        $this->assertEquals('500', $converted->getAmount());
        $this->assertEquals('EUR', $converted->getCurrency());
    }

    public function test_it_throws_exception_if_currency_not_found()
    {
        $money = new Money(1000, 'XOF');

        $this->expectException(\Exception::class);
        
        $this->service->convert($money, 'JPY');
    }
}
