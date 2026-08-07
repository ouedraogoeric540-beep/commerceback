<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\KycService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminKycController extends Controller
{
    protected $kycService;

    public function __construct(KycService $kycService)
    {
        $this->kycService = $kycService;
    }

    /**
     * Liste des boutiques en attente de validation KYC (paginée)
     */
    public function pendingShops(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $shops = Shop::with(['user', 'kycDocuments'])
            ->where('status', 'pending')
            ->paginate(15);

        return response()->json(['shops' => $shops]);
    }

    /**
     * Historique des dossiers KYC (approuvés ou rejetés)
     */
    public function history(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $shops = Shop::with(['user', 'kycDocuments'])
            ->whereIn('status', ['approved', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json(['shops' => $shops]);
    }

    /**
     * Approuver une boutique
     */
    public function approve(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $shop = Shop::findOrFail($id);
        
        if ($shop->status !== 'pending') {
            return response()->json(['message' => 'Cette boutique n\'est plus en attente.'], 400);
        }

        try {
            $this->kycService->approveShop($shop);
            AuditLogService::log('kyc.approved', $shop, ['shop_name' => $shop->name]);
            if ($shop->user) {
                \App\Services\NotificationService::send($shop->user, 'Félicitations ! Votre boutique a été validée', "Le dossier KYC de votre boutique {$shop->name} a été accepté.", 'kyc_approved', ['shop_id' => $shop->id]);
            }
            return response()->json(['message' => 'Boutique approuvée avec succès.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de l\'approbation.'], 500);
        }
    }

    /**
     * Rejeter une boutique
     */
    public function reject(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $shop = Shop::findOrFail($id);
        
        if ($shop->status !== 'pending') {
            return response()->json(['message' => 'Cette boutique n\'est plus en attente.'], 400);
        }

        try {
            $this->kycService->rejectShop($shop, $request->reason);
            AuditLogService::log('kyc.rejected', $shop, ['shop_name' => $shop->name, 'reason' => $request->reason]);
            if ($shop->user) {
                \App\Services\NotificationService::send($shop->user, 'Dossier KYC Rejeté', "Le dossier KYC de votre boutique {$shop->name} a été refusé pour le motif suivant : {$request->reason}.", 'kyc_rejected', ['shop_id' => $shop->id, 'reason' => $request->reason]);
            }
            return response()->json(['message' => 'Boutique rejetée avec succès.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors du rejet.'], 500);
        }
    }
}
