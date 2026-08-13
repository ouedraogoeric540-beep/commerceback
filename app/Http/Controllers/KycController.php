<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KycDocument;
use App\Contracts\StorageServiceInterface;
use Illuminate\Support\Str;

class KycController extends Controller
{
    protected StorageServiceInterface $storage;

    public function __construct(StorageServiceInterface $storage)
    {
        $this->storage = $storage;
    }

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
            return response()->json(['message' => __('api.vous_navez_pas_de_boutique')], 403);
        }

        $rectoFile = $request->file('document_recto');
        $rectoName = Str::random(10) . '.' . $rectoFile->getClientOriginalExtension();
        $rectoPath = "users/{$user->id}/kyc/{$rectoName}";
        $this->storage->upload('user-files', $rectoPath, $rectoFile);

        $versoPath = null;
        if ($request->hasFile('document_verso')) {
            $versoFile = $request->file('document_verso');
            $versoName = Str::random(10) . '.' . $versoFile->getClientOriginalExtension();
            $versoPath = "users/{$user->id}/kyc/{$versoName}";
            $this->storage->upload('user-files', $versoPath, $versoFile);
        }

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
            'message' => __('api.document_soumis_avec_succ_s'),
            'kyc' => $kyc,
            'shop' => $shop->load('kycDocuments')
        ], 201);
    }
}
