<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class DisputeService
{
    /**
     * Resolve a dispute.
     */
    public function resolveDispute(Dispute $dispute, string $resolution, ?string $adminNotes = null)
    {
        DB::beginTransaction();
        try {
            if ($dispute->status === 'closed' || $dispute->status === 'buyer_won' || $dispute->status === 'seller_won') {
                throw new Exception("Ce litige a déjà été traité.");
            }

            $dispute->status = $resolution;
            if ($adminNotes) {
                $dispute->admin_notes = $adminNotes;
            }
            $dispute->save();

            // If the buyer wins, we might need to issue a refund.
            if ($resolution === 'buyer_won') {
                $order = $dispute->order;
                $shop = $dispute->shop;
                
                if ($shop && $shop->wallet) {
                    // For the MVP, we assume the shop's wallet still has the funds (or we allow it to go negative)
                    // Find the original credit transaction for this order
                    $transaction = WalletTransaction::where('wallet_id', $shop->wallet->id)
                        ->where('reference_type', 'Order')
                        ->where('reference_id', $order->id)
                        ->where('type', 'credit')
                        ->first();
                        
                    if ($transaction) {
                        // Reverse the seller's earnings
                        $shop->wallet->balance -= $transaction->amount;
                        $shop->wallet->save();
                        
                        WalletTransaction::create([
                            'wallet_id' => $shop->wallet->id,
                            'type' => 'debit',
                            'amount' => $transaction->amount,
                            'description' => "Remboursement (Litige perdu) - Commande #{$order->id}",
                            'reference_type' => 'Dispute',
                            'reference_id' => $dispute->id
                        ]);
                    }
                    
                    // We also need to reverse the platform commission if requested,
                    // but for this MVP, as discussed, we'll reverse the whole transaction.
                    // This means we also deduct from the platform wallet.
                    $platformWallet = \App\Models\Wallet::whereNull('shop_id')->first();
                    if ($platformWallet) {
                        $platformCommissionTx = WalletTransaction::where('wallet_id', $platformWallet->id)
                            ->where('reference_type', 'Order')
                            ->where('reference_id', $order->id)
                            ->where('type', 'credit')
                            ->first();
                            
                        if ($platformCommissionTx) {
                            $platformWallet->balance -= $platformCommissionTx->amount;
                            $platformWallet->save();
                            
                            WalletTransaction::create([
                                'wallet_id' => $platformWallet->id,
                                'type' => 'debit',
                                'amount' => $platformCommissionTx->amount,
                                'description' => "Annulation de commission (Remboursement Acheteur) - Commande #{$order->id}",
                                'reference_type' => 'Dispute',
                                'reference_id' => $dispute->id
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            return $dispute;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
