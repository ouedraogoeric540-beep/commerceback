<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Setting;
use App\Services\FinanceService;
use Carbon\Carbon;
use Exception;

class ReleaseEscrowFundsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(FinanceService $financeService): void
    {
        // Pull configuration
        $escrowDays = (int) Setting::get('escrow_days', 14); // e.g. 14 days after delivery
        $commissionRate = floatval(Setting::get('commission_rate', 10)) / 100;
        $reserveRate = floatval(Setting::get('rolling_reserve_rate', 5)) / 100;

        // Find orders that are delivered/completed, older than escrow_days, and not yet released
        $eligibleOrders = Order::where('status', 'delivered')
            ->where('escrow_released', false)
            ->where('updated_at', '<=', Carbon::now()->subDays($escrowDays))
            ->get();

        foreach ($eligibleOrders as $order) {
            DB::beginTransaction();
            try {
                // Group by shop
                $itemsByShop = $order->items->groupBy(function($item) {
                    return $item->product->shop_id;
                });

                foreach ($itemsByShop as $shopId => $items) {
                    $shopTotal = $items->sum(function ($item) {
                        return $item->price * $item->quantity;
                    });
                    
                    if ($shopTotal <= 0) continue;

                    $commission = $shopTotal * $commissionRate;
                    $reserve = $shopTotal * $reserveRate;
                    $sellerNet = $shopTotal - $commission - $reserve;

                    $escrowAccount = $financeService->getSellerAccount($shopId, 'escrow');
                    $availableAccount = $financeService->getSellerAccount($shopId, 'available');
                    $reserveAccount = $financeService->getSellerAccount($shopId, 'reserve');
                    $commissionAccount = $financeService->getSystemAccount('platform_commission', 'Platform Commission', 'revenue');

                    // Debit Escrow (liability decreases)
                    // Credit Available, Reserve, Commission
                    // To do this with our helper, we do it in 3 steps or manually.
                    
                    // 1. Escrow to Available (Net)
                    $financeService->recordDoubleEntry($sellerNet, $order->currency_code ?? 'XOF', $escrowAccount, $availableAccount, "Libération Escrow (Net Vendeur) - Cmd #{$order->id}", $order);
                    
                    // 2. Escrow to Commission
                    $financeService->recordDoubleEntry($commission, $order->currency_code ?? 'XOF', $escrowAccount, $commissionAccount, "Commission sur Vente - Cmd #{$order->id}", $order);
                    
                    // 3. Escrow to Reserve
                    $financeService->recordDoubleEntry($reserve, $order->currency_code ?? 'XOF', $escrowAccount, $reserveAccount, "Fonds de Réserve - Cmd #{$order->id}", $order);
                }

                $order->escrow_released = true;
                $order->save();

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                \Log::error("Failed to release escrow for order {$order->id}: " . $e->getMessage());
            }
        }
    }
}
