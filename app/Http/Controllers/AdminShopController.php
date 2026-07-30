<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\KycDocument;

class AdminShopController extends Controller
{
    /**
     * Liste des boutiques en attente de validation KYC
     */
    public function pendingShops(Request $request)
    {
        // Seulement les Admins
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $shops = Shop::with(['user', 'kycDocuments'])
            ->where('status', 'pending')
            ->whereHas('kycDocuments', function ($query) {
                $query->where('status', 'pending');
            })
            ->get();

        return response()->json(['shops' => $shops]);
    }

    /**
     * Approuver une boutique et son KYC
     */
    public function approveShop(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $shop = Shop::findOrFail($id);
        $shop->update(['status' => 'approved']);

        // Approuver tous les documents KYC pending de cette boutique
        $shop->kycDocuments()->where('status', 'pending')->update(['status' => 'approved']);

        return response()->json(['message' => 'Boutique approuvée avec succès.']);
    }

    /**
     * Rejeter une boutique et son KYC
     */
    public function rejectShop(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $shop = Shop::findOrFail($id);
        $shop->update(['status' => 'rejected']);

        // Rejeter le KYC pending avec la raison
        $shop->kycDocuments()->where('status', 'pending')->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        return response()->json(['message' => 'Boutique rejetée.']);
    }
}
