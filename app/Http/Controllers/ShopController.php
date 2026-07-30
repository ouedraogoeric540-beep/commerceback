<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    /**
     * Création de la boutique (Onboarding)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:shops,name',
            'description' => 'required|string',
        ]);

        $user = $request->user();

        // Vérifier si l'utilisateur a déjà une boutique
        if ($user->shop) {
            return response()->json(['message' => 'Vous possédez déjà une boutique.'], 403);
        }

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'status' => 'pending',
        ]);

        // Optionnel : On peut lui donner le rôle Vendeur immédiatement
        // pour qu'il puisse accéder à son interface, 
        // mais ses actions seront restreintes par le statut 'pending' de sa boutique.
        if (!$user->hasRole('Vendeur')) {
            $user->assignRole('Vendeur');
        }

        return response()->json([
            'message' => 'Boutique créée avec succès. En attente de soumission KYC.',
            'shop' => $shop,
            'user' => $user->load('roles', 'shop') // on renvoie le user mis à jour
        ], 201);
    }

    /**
     * Récupérer la boutique de l'utilisateur connecté
     */
    public function myShop(Request $request)
    {
        $shop = $request->user()->shop()->with('kycDocuments')->first();

        if (!$shop) {
            return response()->json(['message' => 'Aucune boutique trouvée.'], 404);
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
            return response()->json(['message' => 'Aucune boutique trouvée.'], 404);
        }

        $request->validate([
            'slug' => 'required|string|max:255|unique:shops,slug,' . $shop->id,
            'description' => 'required|string',
            'slogan' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        $shop->slug = Str::slug($request->slug);
        $shop->description = $request->description;
        $shop->slogan = $request->slogan;

        if ($request->hasFile('logo')) {
            // Stockage public pour le logo
            $logoPath = $request->file('logo')->store('logos', 'public');
            $shop->logo = $logoPath;
        }

        $shop->save();

        return response()->json([
            'message' => 'Boutique mise à jour avec succès.',
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
            return response()->json(['message' => 'Aucune boutique trouvée.'], 404);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.primary_color' => 'nullable|string|max:20',
            'settings.social_facebook' => 'nullable|url|max:255',
            'settings.social_instagram' => 'nullable|url|max:255',
            'settings.social_twitter' => 'nullable|url|max:255',
            'settings.social_tiktok' => 'nullable|url|max:255',
            'settings.post_sale_message' => 'nullable|string',
        ]);

        $shop->settings = $request->settings;
        $shop->save();

        return response()->json([
            'message' => 'Paramètres mis à jour avec succès.',
            'shop' => $shop
        ]);
    }

    /**
     * Mettre à jour les informations légales et bancaires
     */
    public function updateBilling(Request $request)
    {
        $shop = $request->user()->shop;

        if (!$shop) {
            return response()->json(['message' => 'Aucune boutique trouvée.'], 404);
        }

        $validated = $request->validate([
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:255',
        ]);

        $shop->update($validated);

        return response()->json([
            'message' => 'Informations légales et bancaires mises à jour.',
            'shop' => $shop
        ]);
    }
}
