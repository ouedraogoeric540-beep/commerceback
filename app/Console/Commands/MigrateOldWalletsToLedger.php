<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class MigrateOldWalletsToLedger extends Command
{
    protected $signature = 'finance:migrate-wallets';
    protected $description = 'Migrate old wallets to new Ledger Accounts architecture';

    public function handle()
    {
        $this->info("Starting migration of wallets to ledger accounts...");

        // Migrate System Wallet (shop_id = null)
        $systemWallet = \App\Models\Wallet::whereNull('shop_id')->first();
        if ($systemWallet) {
            $ledger = \App\Models\LedgerAccount::firstOrCreate([
                'code' => 'system_cash',
            ], [
                'name' => 'System Cash / Main Bank',
                'type' => 'asset',
                'currency' => $systemWallet->currency,
            ]);
            $this->info("System Cash Ledger Account created/verified.");
        }

        // Migrate Seller Wallets
        $sellerWallets = \App\Models\Wallet::whereNotNull('shop_id')->get();
        foreach ($sellerWallets as $wallet) {
            // Create Seller Liability Account (Available Balance)
            $liabilityAccount = \App\Models\LedgerAccount::firstOrCreate([
                'owner_type' => \App\Models\Shop::class,
                'owner_id' => $wallet->shop_id,
                'wallet_type' => 'available',
            ], [
                'name' => "Shop {$wallet->shop_id} - Available Balance",
                'type' => 'liability',
                'currency' => $wallet->currency,
            ]);

            // Create Escrow Account for the seller
            \App\Models\LedgerAccount::firstOrCreate([
                'owner_type' => \App\Models\Shop::class,
                'owner_id' => $wallet->shop_id,
                'wallet_type' => 'escrow',
            ], [
                'name' => "Shop {$wallet->shop_id} - Escrow Balance",
                'type' => 'liability', // Still owed to seller, but locked
                'currency' => $wallet->currency,
            ]);

            $this->info("Migrated Wallet for Shop {$wallet->shop_id}");
        }

        $this->info("Wallet migration completed!");
    }
}
