<?php

namespace App\Services\Payment;

use App\Services\Payment\Providers\PaymentProviderInterface;
use App\Services\Payment\Providers\SimulatedPaymentProvider;

class PaymentFactory
{
    /**
     * Create the appropriate payment provider instance.
     *
     * @param string $providerName
     * @return PaymentProviderInterface
     * @throws \Exception
     */
    public static function create(string $providerName = 'simulated'): PaymentProviderInterface
    {
        switch (strtolower($providerName)) {
            case 'simulated':
                return new SimulatedPaymentProvider();
            // case 'stripe':
            //     return new StripePaymentProvider();
            // case 'cinetpay':
            //     return new CinetPayPaymentProvider();
            default:
                throw new \Exception("Payment provider [{$providerName}] not supported.");
        }
    }
}
