<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\KycDocument;
use App\Services\KycService;
use App\Services\AuditLogService;
use App\Contracts\StorageServiceInterface;
use Illuminate\Http\Request;

class AdminKycController extends Controller
{
    protected $kycService;
    protected StorageServiceInterface $storage;

    public function __construct(KycService $kycService, StorageServiceInterface $storage)
    {
        $this->kycService = $kycService;
        $this->storage = $storage;
    }

    /**
     * Liste des boutiques en attente de validation KYC (paginée)
     */
    public function pendingShops(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => __('api.acc_s_non_autoris')], 403);
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
            return response()->json(['message' => __('api.acc_s_non_autoris')], 403);
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
            return response()->json(['message' => __('api.acc_s_non_autoris')], 403);
        }

        $shop = Shop::findOrFail($id);
        
        if ($shop->status !== 'pending') {
            return response()->json(['message' => __('api.cette_boutique_nest_plus_en_attente')], 400);
        }

        try {
            $this->kycService->approveShop($shop);
            AuditLogService::log('kyc.approved', $shop, ['shop_name' => $shop->name]);
            if ($shop->user) {
                \App\Services\NotificationService::send($shop->user, 'Félicitations ! Votre boutique a été validée', "Le dossier KYC de votre boutique {$shop->name} a été accepté.", 'kyc_approved', ['shop_id' => $shop->id]);
            }
            return response()->json(['message' => __('api.boutique_approuv_e_avec_succ_s')]);
        } catch (\Exception $e) {
            return response()->json(['message' => __('api.erreur_lors_de_lapprobation')], 500);
        }
    }

    /**
     * Rejeter une boutique
     */
    public function reject(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => __('api.acc_s_non_autoris')], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $shop = Shop::findOrFail($id);
        
        if ($shop->status !== 'pending') {
            return response()->json(['message' => __('api.cette_boutique_nest_plus_en_attente')], 400);
        }

        try {
            $this->kycService->rejectShop($shop, $request->reason);
            AuditLogService::log('kyc.rejected', $shop, ['shop_name' => $shop->name, 'reason' => $request->reason]);
            if ($shop->user) {
                \App\Services\NotificationService::send($shop->user, 'Dossier KYC Rejeté', "Le dossier KYC de votre boutique {$shop->name} a été refusé pour le motif suivant : {$request->reason}.", 'kyc_rejected', ['shop_id' => $shop->id, 'reason' => $request->reason]);
            }
            return response()->json(['message' => __('api.boutique_rejet_e_avec_succ_s')]);
        } catch (\Exception $e) {
            return response()->json(['message' => __('api.erreur_lors_du_rejet')], 500);
        }
    }

    /**
     * Voir un document KYC (Génère une URL signée)
     */
    public function viewDocument(Request $request, $id, $side)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => __('api.acc_s_non_autoris')], 403);
        }

        $kyc = KycDocument::findOrFail($id);
        
        $path = $side === 'recto' ? $kyc->document_recto : $kyc->document_verso;

        if (!$path || !$this->storage->exists('user-files', $path)) {
            return response()->json(['message' => __('api.file_not_found')], 404);
        }

        $url = $this->storage->temporaryUrl('user-files', $path, 60);

        if (!$url) {
            return response()->json(['message' => __('api.erreur_lors_de_la_g_n_ration_d')], 500);
        }

        return redirect()->away($url);
    }
}
