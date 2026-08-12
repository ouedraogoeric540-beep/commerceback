<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
        $tables = [
            'wallet_transactions',
            'disputes',
            'dispute_messages',
            'country_payment_configs',
            'payment_provider_configs',
            'payment_method_configs',
            'commission_configs',
            'withdrawal_configs',
            'payment_intents',
            'payments',
            'commission_snapshots',
            'ledger_accounts',
            'ledger_transactions',
            'ledger_entries',
            'wallets',
            'wallet_holds',
            'withdrawals',
            'financial_securities',
            'financial_audit_logs',
            'financial_anomalies',
            'financial_outbox_events',
            'download_logs'
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function down(): void
    {
        // One way trip.
    }
};
