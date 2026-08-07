<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;

interface PaymentProviderInterface
{
    /**
     * Initializes a payment session and returns a checkout URL.
     *
     * @param Order $order
     * @param string $successUrl
     * @param string $cancelUrl
     * @return string The URL to redirect the user to.
     */
    public function initializePayment(Order $order, string $successUrl, string $cancelUrl): string;

    /**
     * Validates a webhook payload to ensure it's authentic.
     *
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function validateWebhook(array $payload, string $signature): bool;

    /**
     * Extracts the transaction ID and status from the webhook payload.
     *
     * @param array $payload
     * @return \App\Services\Payment\DTOs\PaymentResult
     */
    public function processWebhook(array $payload): \App\Services\Payment\DTOs\PaymentResult;
}
