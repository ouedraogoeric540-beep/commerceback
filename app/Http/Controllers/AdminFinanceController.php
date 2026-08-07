<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Withdrawal;
use App\Services\FinanceService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanceController extends Controller
{
    protected $financeService;

    public function __construct(FinanceService $financeService)
    {
        $this->financeService = $financeService;
    }

    /**
     * Get global financial statistics.
     */
    public function getStats()
    {
        $platformCommissionAccount = $this->financeService->getSystemAccount('platform_commission', 'Platform Commission', 'revenue');
        $totalCommissions = $platformCommissionAccount->balance;
        
        $totalSellerBalances = LedgerAccount::whereNotNull('owner_id')->sum('balance');
        
        $systemCashAccount = $this->financeService->getSystemAccount('system_cash', 'System Cash / Main Bank', 'asset');
        $systemCash = $systemCashAccount->balance;

        $pendingWithdrawalsAccount = $this->financeService->getSystemAccount('pending_withdrawals', 'Pending Withdrawals', 'liability');
        $pendingWithdrawals = $pendingWithdrawalsAccount->balance;

        $totalWithdrawn = Withdrawal::where('status', 'approved')->sum('amount');

        return response()->json([
            'totalCommissions' => $totalCommissions,
            'totalSellerBalances' => $totalSellerBalances,
            'totalWithdrawn' => $totalWithdrawn,
            'systemCash' => $systemCash,
            'pendingWithdrawals' => $pendingWithdrawals,
            'currency' => 'XOF'
        ]);
    }

    /**
     * Get paginated withdrawals.
     */
    public function getWithdrawals(Request $request)
    {
        $status = $request->query('status');
        
        $query = Withdrawal::with('shop:id,name,user_id')->latest();
        
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Process a withdrawal.
     */
    public function processWithdrawal(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string'
        ]);

        $withdrawal = Withdrawal::findOrFail($id);

        try {
            $withdrawal = $this->financeService->processWithdrawal(
                $withdrawal,
                $request->status,
                $request->admin_notes
            );

            $shop = \App\Models\Shop::with('user')->find($withdrawal->shop_id);
            if ($shop && $shop->user) {
                $statusLabel = $request->status === 'approved' ? 'acceptée' : 'rejetée';
                \App\Services\NotificationService::send(
                    $shop->user,
                    "Demande de retrait {$statusLabel}",
                    "Votre demande de retrait de {$withdrawal->amount} XOF a été {$statusLabel}. Note admin: " . ($request->admin_notes ?? 'aucune'),
                    'withdrawal_processed',
                    ['withdrawal_id' => $withdrawal->id, 'status' => $request->status]
                );
            }

            AuditLogService::log('withdrawal.' . $request->status, $withdrawal, [
                'amount'    => $withdrawal->amount,
                'shop_id'   => $withdrawal->shop_id,
            ]);

            return response()->json([
                'message' => 'Statut du retrait mis à jour avec succès.',
                'withdrawal' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get paginated transactions.
     */
    public function getTransactions()
    {
        $transactions = LedgerTransaction::with('entries.account')
            ->latest()
            ->paginate(50);
            
        return response()->json($transactions);
    }
}
