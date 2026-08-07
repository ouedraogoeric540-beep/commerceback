<?php

namespace App\Http\Controllers;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Withdrawal;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerFinanceController extends Controller
{
    protected $financeService;

    public function __construct(FinanceService $financeService)
    {
        $this->financeService = $financeService;
    }

    private function getShop()
    {
        $user = Auth::user();
        if (!$user || !$user->shop) {
            abort(403, "Boutique non trouvée.");
        }
        return $user->shop;
    }

    public function getStats()
    {
        $shop = $this->getShop();
        
        $availableAccount = $this->financeService->getSellerAccount($shop->id, 'available');
        $escrowAccount = $this->financeService->getSellerAccount($shop->id, 'escrow');
        $reserveAccount = $this->financeService->getSellerAccount($shop->id, 'reserve');

        $totalWithdrawn = Withdrawal::where('shop_id', $shop->id)
            ->where('status', 'approved')
            ->sum('amount');
            
        $pendingWithdrawal = Withdrawal::where('shop_id', $shop->id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'balance' => $availableAccount->balance,
            'escrow' => $escrowAccount->balance,
            'reserve' => $reserveAccount->balance,
            'totalWithdrawn' => $totalWithdrawn,
            'pendingWithdrawal' => $pendingWithdrawal,
            'currency' => $availableAccount->currency
        ]);
    }

    public function getTransactions()
    {
        $shop = $this->getShop();
        
        $availableAccount = $this->financeService->getSellerAccount($shop->id, 'available');
        $escrowAccount = $this->financeService->getSellerAccount($shop->id, 'escrow');
        $reserveAccount = $this->financeService->getSellerAccount($shop->id, 'reserve');
        
        $accountIds = [$availableAccount->id, $escrowAccount->id, $reserveAccount->id];

        // Fetch entries related to any of the seller's accounts
        $entries = LedgerEntry::with('transaction')
            ->whereIn('ledger_account_id', $accountIds)
            ->latest()
            ->paginate(20);

        // Map entries to frontend format
        $entries->getCollection()->transform(function ($entry) {
            $walletType = $entry->account->wallet_type;
            $data = [
                'id' => $entry->id,
                'type' => $entry->type, // debit or credit
                'amount' => $entry->amount,
                'description' => $entry->transaction->description,
                'created_at' => $entry->created_at,
                'wallet_type' => $walletType, // available, escrow, reserve
            ];

            // Si c'est un crédit sur le compte séquestre, on estime la date de libération (ex: 14 jours)
            if ($walletType === 'escrow' && $entry->type === 'credit') {
                $data['release_date'] = $entry->created_at->addDays(14)->toISOString();
            }

            return $data;
        });

        return response()->json($entries);
    }

    public function getWithdrawals()
    {
        $shop = $this->getShop();
        
        $withdrawals = Withdrawal::where('shop_id', $shop->id)
            ->latest()
            ->paginate(20);

        return response()->json($withdrawals);
    }

    public function requestWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100', // Minimum 100
            'payment_method' => 'required|string',
            'details' => 'nullable|array' // For things like IBAN, Phone number, etc.
        ]);

        $shop = $this->getShop();

        try {
            $withdrawal = $this->financeService->requestWithdrawal(
                $shop,
                $request->amount,
                $request->payment_method,
                $request->details ?? []
            );

            return response()->json([
                'message' => 'Demande de retrait envoyée avec succès. Elle sera traitée prochainement.',
                'withdrawal' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
