<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use App\Models\KycDocument;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    /**
     * Création de la boutique (Onboarding)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:shops,name',
            'description' => 'nullable|string',
            'support_phone' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'primary_color' => 'nullable|string|max:20',
            'kyc_type' => 'required|in:id_card,passport,company_registration,proof_of_address',
            'document_recto' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_verso' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        // Vérifier si l'utilisateur a déjà une boutique
        if ($user->shop) {
            return response()->json(['message' => __('api.vous_poss_dez_d_j_une_boutique')], 403);
        }

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'support_phone' => $request->support_phone,
            'address' => $request->address,
            'status' => 'pending',
        ]);

        if ($request->hasFile('logo')) {
            $shop->logo = $request->file('logo')->store('logos', 'public');
        }

        if ($request->filled('primary_color')) {
            $shop->settings = ['primary_color' => $request->primary_color];
        }

        $shop->save();

        $rectoPath = $request->file('document_recto')->store('kyc', 'public');
        $versoPath = $request->hasFile('document_verso') ? $request->file('document_verso')->store('kyc', 'public') : null;

        KycDocument::create([
            'shop_id' => $shop->id,
            'type' => $request->kyc_type,
            'document_recto' => $rectoPath,
            'document_verso' => $versoPath,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => __('api.demande_envoy_e_avec_succ_s_l'),
            'shop' => $shop,
            'user' => $user->load('roles', 'shop') // on renvoie le user mis à jour
        ], 201);
    }

    /**
     * Création de compte et boutique simultanée pour les visiteurs (Guest)
     */
    public function onboardGuest(Request $request)
    {
        $request->validate([
            // User validation
            'user_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            
            // Shop validation
            'name' => 'required|string|max:255|unique:shops,name',
            'description' => 'nullable|string',
            'support_phone' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'primary_color' => 'nullable|string|max:20',
            'kyc_type' => 'required|in:id_card,passport,company_registration,proof_of_address',
            'document_recto' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_verso' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // 1. Créer l'utilisateur
            $user = User::create([
                'name' => $request->user_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Assigner le rôle Vendeur
            $user->assignRole('Vendeur');

            // 2. Créer la boutique
            $shop = Shop::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'support_phone' => $request->support_phone,
                'address' => $request->address,
                'status' => 'pending',
            ]);

            if ($request->hasFile('logo')) {
                $shop->logo = $request->file('logo')->store('logos', 'public');
            }

            if ($request->filled('primary_color')) {
                $shop->settings = ['primary_color' => $request->primary_color];
            }

            $shop->save();

            $rectoPath = $request->file('document_recto')->store('kyc', 'public');
            $versoPath = $request->hasFile('document_verso') ? $request->file('document_verso')->store('kyc', 'public') : null;

            KycDocument::create([
                'shop_id' => $shop->id,
                'type' => $request->kyc_type,
                'document_recto' => $rectoPath,
                'document_verso' => $versoPath,
                'status' => 'pending'
            ]);

            // 3. Générer le token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => __('api.compte_et_boutique_cr_s_avec_s'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->load('roles', 'shop'),
                'shop' => $shop
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => __('api.une_erreur_est_survenue_lors_d'), 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupérer la boutique de l'utilisateur connecté
     */
    public function myShop(Request $request)
    {
        $shop = $request->user()->shop()->with('kycDocuments')->first();

        if (!$shop) {
            return response()->json(['message' => __('api.aucune_boutique_trouv_e')], 404);
        }

        return response()->json([
            'shop' => $shop
        ]);
    }

    /**
     * Mettre à jour les paramètres de la boutique
     */
    public function update(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => __('api.aucune_boutique_trouv_e')], 404);
        }

        $request->validate([
            'slug' => 'required|string|max:255|unique:shops,slug,' . $shop->id,
            'description' => 'required|string',
            'slogan' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:255',
        ]);

        $shop->slug = Str::slug($request->slug);
        $shop->description = $request->description;
        $shop->slogan = $request->slogan;
        $shop->support_email = $request->support_email;
        $shop->support_phone = $request->support_phone;
        $shop->address = $request->address;
        $shop->city = $request->city;
        $shop->postal_code = $request->postal_code;
        $shop->country = $request->country;
        $shop->registration_number = $request->registration_number;
        $shop->vat_number = $request->vat_number;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
            $shop->logo = $logoPath;
        }

        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('covers', 'public');
            $shop->cover = $coverPath;
        }

        $shop->save();

        return response()->json([
            'message' => __('api.boutique_mise_jour_avec_succ_s'),
            'shop' => $shop->fresh('kycDocuments')
        ]);
    }

    /**
     * Vérifier si un slug est disponible (pour validation en temps réel)
     */
    public function checkSlug(Request $request)
    {
        $slug = $request->query('slug');
        $shopId = $request->user()->shop ? $request->user()->shop->id : null;

        if (!$slug) {
            return response()->json(['available' => false]);
        }

        $query = Shop::where('slug', Str::slug($slug));
        
        // Exclure la boutique actuelle de la vérification
        if ($shopId) {
            $query->where('id', '!=', $shopId);
        }

        $exists = $query->exists();

        return response()->json([
            'available' => !$exists,
            'suggestion' => $exists ? Str::slug($slug) . '-' . rand(10, 99) : null
        ]);
    }

    /**
     * Mettre à jour les paramètres de personnalisation de la boutique (couleurs, réseaux sociaux)
     */
    public function updateSettings(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => __('api.aucune_boutique_trouv_e')], 404);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.primary_color' => 'nullable|string|max:20',
            'settings.social_facebook' => 'nullable|url|max:255',
            'settings.social_instagram' => 'nullable|url|max:255',
            'settings.social_twitter' => 'nullable|url|max:255',
            'settings.social_tiktok' => 'nullable|url|max:255',
            'settings.post_sale_message' => 'nullable|string',
            'settings.business_hours' => 'nullable|string',
            'settings.return_policy' => 'nullable|string',
            'settings.terms_of_sale' => 'nullable|string',
            'settings.accepted_payments' => 'nullable|array',
            'settings.storefront_sections' => 'nullable|array',
        ]);

        $shop->settings = $request->settings;
        $shop->save();

        return response()->json([
            'message' => __('api.param_tres_mis_jour_avec_succ_'),
            'shop' => $shop
        ]);
    }


}
