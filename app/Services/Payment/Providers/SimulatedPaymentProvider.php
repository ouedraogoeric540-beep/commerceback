<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;
use App\Services\Payment\DTOs\PaymentResult;
use Illuminate\Support\Str;

class SimulatedPaymentProvider implements PaymentProviderInterface
{
    public function initializePayment(Order $order, string $successUrl, string $cancelUrl): string
    {
        // For simulation, we'll return a simulated gateway URL where the frontend 
        // will mock user interaction before sending a webhook/success callback.
        return url('/api/checkout/simulate-gateway?order_id=' . $order->id . '&amount=' . $order->total_amount);
    }

    public function validateWebhook(array $payload, string $signature): bool
    {
        // Simulate webhook validation (always true for the simulation)
        return true;
    }

    public function processWebhook(array $payload): PaymentResult
    {
        // Simulate extracting payload data
        $orderId = $payload['order_id'] ?? '';
        $status = $payload['status'] ?? 'failed';
        $transactionId = $payload['transaction_id'] ?? 'simulated_txn_' . Str::random(10);
        
        return new PaymentResult($orderId, $transactionId, $status);
    }
}
