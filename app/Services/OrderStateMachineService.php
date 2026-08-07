<?php

namespace App\Services;

use Exception;

class OrderStateMachineService
{
    protected array $transitions = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['preparing', 'shipped', 'cancelled'],
        'preparing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Checks if a transition is valid.
     * 
     * @param string $currentStatus
     * @param string $nextStatus
     * @return bool
     */
    public function canTransition(string $currentStatus, string $nextStatus): bool
    {
        $allowed = $this->transitions[$currentStatus] ?? [];
        return in_array($nextStatus, $allowed);
    }

    /**
     * Assert a transition is valid, throwing an exception if not.
     *
     * @param string $currentStatus
     * @param string $nextStatus
     * @throws Exception
     */
    public function assertCanTransition(string $currentStatus, string $nextStatus): void
    {
        if (!$this->canTransition($currentStatus, $nextStatus)) {
            throw new Exception("Transition invalide de '{$currentStatus}' vers '{$nextStatus}'");
        }
    }
}
