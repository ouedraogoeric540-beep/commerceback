<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\Withdrawal;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class FinanceService
{
    /**
     * Helper to get a system account.
     */
    public function getSystemAccount($code, $name, $type)
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $type, 'currency' => 'XOF']
        );
    }

    /**
     * Helper to get a seller account (available, escrow, reserve).
     */
    public function getSellerAccount($shopId, $walletType)
    {
        $names = [
            'available' => 'Available Balance',
            'escrow' => 'Escrow Balance',
            'reserve' => 'Reserve Balance',
        ];

        return LedgerAccount::firstOrCreate([
            'owner_type' => Shop::class,
            'owner_id' => $shopId,
            'wallet_type' => $walletType,
        ], [
            'name' => "Shop {$shopId} - " . ($names[$walletType] ?? 'Account'),
            'type' => 'liability',
            'currency' => 'XOF',
        ]);
    }

    /**
     * Helper to record a balanced transaction.
     */
    public function recordDoubleEntry(
        $amount,
        $currency,
        LedgerAccount $debitAccount,
        LedgerAccount $creditAccount,
        $description,
        $reference = null,
        $idempotencyKey = null
    ) {
        if ($amount <= 0) {
            return null;
        }

        $transaction = LedgerTransaction::create([
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
            'status' => 'completed',
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
        ]);

        LedgerEntry::create([
            'ledger_transaction_id' => $transaction->id,
            'ledger_account_id' => $debitAccount->id,
            'type' => 'debit',
            'amount' => $amount,
            'currency' => $currency,
        ]);

        LedgerEntry::create([
            'ledger_transaction_id' => $transaction->id,
            'ledger_account_id' => $creditAccount->id,
            'type' => 'credit',
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $transaction;
    }

    /**
     * Process buyer payment for an order.
     */
    public function creditSellerForOrder(Order $order)
    {
        DB::beginTransaction();
        try {
            $systemCash = $this->getSystemAccount('system_cash', 'System Cash / Main Bank', 'asset');

            $itemsByShop = $order->items->groupBy(function($item) {
                return $item->product->shop_id;
            });

            foreach ($itemsByShop as $shopId => $items) {
                $shopTotal = $items->sum(function ($item) {
                    return $item->price * $item->quantity;
                });

                $escrowAccount = $this->getSellerAccount($shopId, 'escrow');

                $this->recordDoubleEntry(
                    $shopTotal,
                    $order->currency_code ?? 'XOF',
                    $systemCash, 
                    $escrowAccount, 
                    "Paiement reçu pour commande #{$order->id}",
                    $order
                );
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Request a withdrawal for a shop.
     */
    public function requestWithdrawal(Shop $shop, float $amount, string $paymentMethod, array $details = [])
    {
        DB::beginTransaction();
        try {
            $availableAccount = $this->getSellerAccount($shop->id, 'available');
            
            if ($availableAccount->balance < $amount) {
                throw new Exception("Solde insuffisant.");
            }

            $withdrawal = Withdrawal::create([
                'shop_id' => $shop->id,
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'details' => $details
            ]);

            $pendingWithdrawalAccount = $this->getSystemAccount('pending_withdrawals', 'Pending Withdrawals', 'liability');

            $this->recordDoubleEntry(
                $amount,
                'XOF',
                $availableAccount,
                $pendingWithdrawalAccount,
                "Demande de retrait #{$withdrawal->id}",
                $withdrawal
            );

            DB::commit();
            
            return $withdrawal;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process a withdrawal (approve or reject).
     */
    public function processWithdrawal(Withdrawal $withdrawal, string $status, ?string $adminNotes = null)
    {
        DB::beginTransaction();
        try {
            if ($withdrawal->status !== 'pending') {
                throw new Exception("Ce retrait a déjà été traité.");
            }

            $withdrawal->status = $status;
            $withdrawal->admin_notes = $adminNotes;
            $withdrawal->save();

            $pendingWithdrawalAccount = $this->getSystemAccount('pending_withdrawals', 'Pending Withdrawals', 'liability');

            if ($status === 'approved') {
                $systemCash = $this->getSystemAccount('system_cash', 'System Cash / Main Bank', 'asset');
                
                $this->recordDoubleEntry(
                    $withdrawal->amount,
                    'XOF',
                    $pendingWithdrawalAccount, 
                    $systemCash, 
                    "Retrait validé et payé #{$withdrawal->id}",
                    $withdrawal
                );
            } elseif ($status === 'rejected') {
                $availableAccount = $this->getSellerAccount($withdrawal->shop_id, 'available');
                
                $this->recordDoubleEntry(
                    $withdrawal->amount,
                    'XOF',
                    $pendingWithdrawalAccount, 
                    $availableAccount, 
                    "Remboursement suite au refus du retrait #{$withdrawal->id}",
                    $withdrawal
                );
            }

            DB::commit();
            return $withdrawal;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
