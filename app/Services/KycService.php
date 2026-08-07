<?php

namespace App\Services;

use App\Models\Shop;
use App\Notifications\ShopStatusNotification;
use Illuminate\Support\Facades\DB;
use Exception;

class KycService
{
    /**
     * Approves a shop's KYC.
     *
     * @param Shop $shop
     * @return Shop
     * @throws Exception
     */
    public function approveShop(Shop $shop): Shop
    {
        DB::beginTransaction();
        try {
            $shop->update(['status' => 'approved']);

            // Approuver tous les documents KYC pending de cette boutique
            $shop->kycDocuments()->where('status', 'pending')->update(['status' => 'approved']);
            
            // Notification au vendeur
            if ($shop->user) {
                $shop->user->notify(new ShopStatusNotification($shop, 'approved'));
            }

            DB::commit();
            return $shop;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Rejects a shop's KYC with a reason.
     *
     * @param Shop $shop
     * @param string $reason
     * @return Shop
     * @throws Exception
     */
    public function rejectShop(Shop $shop, string $reason): Shop
    {
        DB::beginTransaction();
        try {
            $shop->update(['status' => 'rejected']);

            // Rejeter le KYC pending avec la raison
            $shop->kycDocuments()->where('status', 'pending')->update([
                'status' => 'rejected',
                'rejection_reason' => $reason
            ]);
            
            // Notification au vendeur
            if ($shop->user) {
                $shop->user->notify(new ShopStatusNotification($shop, 'rejected'));
            }

            DB::commit();
            return $shop;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
