<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KycDocument;

class KycController extends Controller
{
    /**
     * Soumettre un document KYC
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:id_card,passport,company_registration,proof_of_address',
            'document_recto' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'document_verso' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json(['message' => 'Vous n\'avez pas de boutique.'], 403);
        }

        // Pour le MVP, on stocke dans 'public' pour pouvoir les afficher facilement via URL
        // En production, on utiliserait un disque privé S3 avec des Signed URLs.
        $rectoPath = $request->file('document_recto')->store('kyc', 'public');
        $versoPath = $request->hasFile('document_verso') ? $request->file('document_verso')->store('kyc', 'public') : null;

        $kyc = KycDocument::create([
            'shop_id' => $shop->id,
            'type' => $request->type,
            'document_recto' => $rectoPath,
            'document_verso' => $versoPath,
            'status' => 'pending'
        ]);

        if ($shop->status === 'rejected') {
            $shop->update(['status' => 'pending']);
        }

        return response()->json([
            'message' => 'Document soumis avec succès.',
            'kyc' => $kyc,
            'shop' => $shop->load('kycDocuments')
        ], 201);
    }
}
