<?php

namespace App\Modules\Currency\ValueObjects;

use InvalidArgumentException;
use RuntimeException;

class Money
{
    private string $amount; // On stocke en string pour la précision avec bcmath
    private string $currencyCode;

    /**
     * Money constructor.
     * @param string|float|int $amount Le montant (stocké avec précision)
     * @param string $currencyCode Le code de la devise (ex: XOF, USD)
     */
    public function __construct($amount, string $currencyCode)
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException("Amount must be numeric.");
        }
        
        if ($amount < 0) {
            throw new InvalidArgumentException("Amount cannot be negative unless explicitly allowed by business logic.");
        }

        $this->amount = (string) $amount;
        $this->currencyCode = strtoupper($currencyCode);
    }

    /**
     * Récupère le montant.
     * @return string
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * Récupère la devise.
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currencyCode;
    }

    /**
     * Vérifie si deux objets Money ont la même devise.
     */
    public function isSameCurrency(Money $other): bool
    {
        return $this->currencyCode === $other->getCurrency();
    }

    /**
     * Additionne un objet Money à un autre.
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        $newAmount = bcadd($this->amount, $other->getAmount(), 6);
        return new self($newAmount, $this->currencyCode);
    }

    /**
     * Soustrait un objet Money d'un autre.
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        $newAmount = bcsub($this->amount, $other->getAmount(), 6);
        
        if ($newAmount < 0) {
            throw new RuntimeException("Resulting money amount cannot be negative.");
        }
        
        return new self($newAmount, $this->currencyCode);
    }

    /**
     * Multiplie l'objet Money par un facteur.
     */
    public function multiply($multiplier): self
    {
        if (!is_numeric($multiplier)) {
            throw new InvalidArgumentException("Multiplier must be numeric.");
        }
        
        $newAmount = bcmul($this->amount, (string)$multiplier, 6);
        return new self($newAmount, $this->currencyCode);
    }

    /**
     * Divise l'objet Money par un diviseur.
     */
    public function divide($divisor): self
    {
        if (!is_numeric($divisor) || $divisor == 0) {
            throw new InvalidArgumentException("Divisor must be numeric and not zero.");
        }
        
        $newAmount = bcdiv($this->amount, (string)$divisor, 6);
        return new self($newAmount, $this->currencyCode);
    }

    /**
     * Formate le montant selon les décimales requises de sa devise.
     * Attention : Ceci n'est qu'un utilitaire basique, la vraie logique
     * devrait passer par un service Intl côté front ou backend étendu.
     */
    public function format(int $decimals = 2): string
    {
        return number_format((float)$this->amount, $decimals, '.', '') . ' ' . $this->currencyCode;
    }

    /**
     * Lève une exception si les devises sont différentes.
     */
    private function assertSameCurrency(Money $other): void
    {
        if (!$this->isSameCurrency($other)) {
            throw new InvalidArgumentException(
                "Currency mismatch: Cannot operate on {$this->currencyCode} and {$other->getCurrency()} without explicit conversion."
            );
        }
    }
}
