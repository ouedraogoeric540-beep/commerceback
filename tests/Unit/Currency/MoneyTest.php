<?php

namespace Tests\Unit\Currency;

use PHPUnit\Framework\TestCase;
use App\Modules\Currency\ValueObjects\Money;
use InvalidArgumentException;
use RuntimeException;

class MoneyTest extends TestCase
{
    public function test_it_creates_a_money_object()
    {
        $money = new Money(2500, 'XOF');
        $this->assertEquals('2500', $money->getAmount());
        $this->assertEquals('XOF', $money->getCurrency());
    }

    public function test_it_throws_if_amount_is_negative()
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-500, 'USD');
    }

    public function test_it_adds_same_currency()
    {
        $m1 = new Money(100.50, 'EUR');
        $m2 = new Money(50.25, 'EUR');
        $result = $m1->add($m2);

        $this->assertEquals('150.750000', $result->getAmount());
        $this->assertEquals('EUR', $result->getCurrency());
    }

    public function test_it_throws_on_cross_currency_addition()
    {
        $m1 = new Money(100, 'EUR');
        $m2 = new Money(100, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $m1->add($m2);
    }

    public function test_it_subtracts_same_currency()
    {
        $m1 = new Money(100, 'XOF');
        $m2 = new Money(40, 'XOF');
        $result = $m1->subtract($m2);

        $this->assertEquals('60.000000', $result->getAmount());
    }

    public function test_it_throws_on_subtraction_resulting_in_negative()
    {
        $m1 = new Money(50, 'USD');
        $m2 = new Money(100, 'USD');

        $this->expectException(RuntimeException::class);
        $m1->subtract($m2);
    }

    public function test_it_multiplies_correctly()
    {
        $m = new Money(10.50, 'USD');
        $result = $m->multiply(3);

        $this->assertEquals('31.500000', $result->getAmount());
    }

    public function test_it_divides_correctly()
    {
        $m = new Money(100, 'USD');
        $result = $m->divide(3);

        // 100 / 3 = 33.333333
        $this->assertEquals('33.333333', $result->getAmount());
    }
}
