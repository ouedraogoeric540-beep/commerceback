<?php

namespace App\Services\Payment\DTOs;

class PaymentResult
{
    public string $orderId;
    public string $transactionId;
    public string $status;
    public ?string $errorMessage;

    public function __construct(string $orderId, string $transactionId, string $status, ?string $errorMessage = null)
    {
        $this->orderId = $orderId;
        $this->transactionId = $transactionId;
        $this->status = $status; // 'success', 'failed', 'pending'
        $this->errorMessage = $errorMessage;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }
}
