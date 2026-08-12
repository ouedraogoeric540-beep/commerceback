<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\Shop;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    /**
     * Liste les codes promo de la boutique du vendeur connecté.
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => __('api.boutique_introuvable')], 404);
        }

        $promoCodes = $shop->promoCodes()->orderBy('created_at', 'desc')->get();
        return response()->json(['promo_codes' => $promoCodes]);
    }

    /**
     * Crée un nouveau code promo.
     */
    public function store(Request $request)
    {
        $shop = $request->user()->shop;
        if (!$shop) {
            return response()->json(['message' => __('api.boutique_introuvable')], 404);
        }

        $request->validate([
            'code' => 'required|string|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $code = strtoupper(trim($request->code));

        // Vérifier l'unicité pour cette boutique
        if (PromoCode::where('shop_id', $shop->id)->where('code', $code)->exists()) {
            return response()->json(['errors' => ['code' => ['Ce code existe déjà pour votre boutique.']]], 422);
        }

        $promoCode = PromoCode::create([
            'shop_id' => $shop->id,
            'code' => $code,
            'type' => $request->type,
            'value' => $request->value,
            'min_amount' => $request->min_amount,
            'max_uses' => $request->max_uses,
            'expires_at' => $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null,
            'is_active' => true,
        ]);

        return response()->json(['message' => __('api.code_promo_cr_avec_succ_s'), 'promo_code' => $promoCode], 201);
    }

    /**
     * Met à jour un code promo (notamment l'activation/désactivation).
     */
    public function update(Request $request, $id)
    {
        $shop = $request->user()->shop;
        $promoCode = PromoCode::where('shop_id', $shop->id)->where('id', $id)->firstOrFail();

        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $promoCode->is_active = $request->is_active;
        $promoCode->save();

        return response()->json(['message' => __('api.code_promo_mis_jour'), 'promo_code' => $promoCode]);
    }

    /**
     * Supprime un code promo.
     */
    public function destroy(Request $request, $id)
    {
        $shop = $request->user()->shop;
        $promoCode = PromoCode::where('shop_id', $shop->id)->where('id', $id)->firstOrFail();
        
        $promoCode->delete();

        return response()->json(['message' => __('api.code_promo_supprim')]);
    }

    /**
     * Valide un code promo depuis le checkout (public).
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'shop_ids' => 'required|array',
            'shop_ids.*' => 'exists:shops,id'
        ]);

        $code = strtoupper(trim($request->code));
        $promoCode = PromoCode::whereIn('shop_id', $request->shop_ids)
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if (!$promoCode) {
            return response()->json(['message' => __('api.code_promo_invalide')], 404);
        }

        if (!$promoCode->is_active) {
            return response()->json(['message' => __('api.ce_code_promo_est_d_sactiv')], 400);
        }

        if ($promoCode->usage_limit !== null && $promoCode->used_count >= $promoCode->usage_limit) {
            return response()->json(['message' => __('api.ce_code_promo_a_atteint_sa_limite_dutilisation')], 400);
        }

        if ($promoCode->expires_at && now()->greaterThan($promoCode->expires_at)) {
            return response()->json(['message' => __('api.ce_code_promo_a_expir')], 400);
        }

        return response()->json([
            'message' => __('api.code_promo_appliqu'),
            'promo_code' => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'type' => $promoCode->type,
                'value' => $promoCode->value,
                'min_amount' => $promoCode->min_amount,
                'shop_id' => $promoCode->shop_id
            ]
        ]);
    }
}
