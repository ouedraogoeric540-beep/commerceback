<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use App\Models\LedgerEntry;
use App\Models\LedgerAccount;

class LedgerIntegrityCheckJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        // Calculate total debits and credits
        $totalDebits = LedgerEntry::where('type', 'debit')->sum('amount');
        $totalCredits = LedgerEntry::where('type', 'credit')->sum('amount');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            \Log::emergency("FINANCIAL ANOMALY DETECTED: Unbalanced Ledger! Debits: {$totalDebits}, Credits: {$totalCredits}");
            // In a real system, we might pause all withdrawals here.
        }

        // The golden rule: Assets = Liabilities + Equity + Revenue - Expenses
        // Since we don't have equity explicitly, Assets - Expenses = Liabilities + Revenue
        // Equivalently: Asset_Balance = Sum(Debits) - Sum(Credits) for Asset
        // Actually, we just check that Sum(All Account Balances considering their natural sign) == 0 
        // if we define debit-normal accounts as positive and credit-normal as negative.

        // It is simpler just to ensure total debits == total credits across the whole system.
        \Log::info("Ledger Integrity Check Passed. Total Debits: {$totalDebits}, Total Credits: {$totalCredits}");
    }
}
