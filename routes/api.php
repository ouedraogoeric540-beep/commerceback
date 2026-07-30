<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\PublicController;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Vitrines publiques
Route::get('/public/shops/{slug}', [App\Http\Controllers\PublicController::class, 'getShop']);
Route::get('/public/products/{id}', [App\Http\Controllers\PublicController::class, 'getProduct']);

// Checkout (Public & Authentifié)
Route::post('/checkout', [App\Http\Controllers\OrderController::class, 'checkout']);
Route::post('/checkout/validate-promo', [App\Http\Controllers\PromoCodeController::class, 'validateCode']);

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    
    // Paramètres de la boutique
    Route::put('/vendor/shop/settings', [ShopController::class, 'updateSettings']);
    Route::put('/vendor/shop/billing', [ShopController::class, 'updateBilling']);

    // Gestion des produits
    Route::post('/vendor/products/reorder', [App\Http\Controllers\ProductController::class, 'reorderProducts']);
    
    // Boutiques
    Route::post('/shops', [ShopController::class, 'store']);
    Route::get('/shops/check-slug', [ShopController::class, 'checkSlug']);
    Route::get('/my-shop', [ShopController::class, 'myShop']);
    Route::post('/my-shop/update', [ShopController::class, 'update']);
    
    // Boutique du vendeur (Gestion)
    Route::get('/my-shop', [ShopController::class, 'myShop']);
    Route::post('/my-shop/update', [ShopController::class, 'update']);
    Route::put('/vendor/shop/billing', [ShopController::class, 'updateBilling']);
    Route::put('/vendor/shop/settings', [ShopController::class, 'updateSettings']);

    // Commandes du vendeur
    Route::get('/seller/stats', [App\Http\Controllers\OrderController::class, 'getSellerStats']);
    Route::get('/seller/orders', [App\Http\Controllers\OrderController::class, 'getSellerOrders']);

    // Espace Acheteur
    Route::get('/buyer/stats', [App\Http\Controllers\OrderController::class, 'getBuyerStats']);
    Route::get('/buyer/orders', [App\Http\Controllers\OrderController::class, 'getBuyerOrders']);
    Route::get('/buyer/orders/{orderId}/download/{productId}', [App\Http\Controllers\OrderController::class, 'downloadDigitalProduct']);

    // Catalogue Vendeur
    Route::get('/seller/products', [App\Http\Controllers\ProductController::class, 'index']);
    Route::post('/seller/products', [App\Http\Controllers\ProductController::class, 'store']);
    Route::put('/seller/products/{id}', [App\Http\Controllers\ProductController::class, 'update']);
    Route::delete('/seller/products/{id}', [App\Http\Controllers\ProductController::class, 'destroy']);

    // Outils Marketing (Codes Promo)
    Route::get('/seller/promo-codes', [App\Http\Controllers\PromoCodeController::class, 'index']);
    Route::post('/seller/promo-codes', [App\Http\Controllers\PromoCodeController::class, 'store']);
    Route::put('/seller/promo-codes/{id}', [App\Http\Controllers\PromoCodeController::class, 'update']);
    Route::delete('/seller/promo-codes/{id}', [App\Http\Controllers\PromoCodeController::class, 'destroy']);

    // KYC
    Route::post('/kyc', [App\Http\Controllers\KycController::class, 'store']);
    
    // Administration (Boutiques & KYC)
    Route::get('/admin/shops/pending', [App\Http\Controllers\AdminShopController::class, 'pendingShops']);
    Route::post('/admin/shops/{id}/approve', [App\Http\Controllers\AdminShopController::class, 'approveShop']);
    Route::post('/admin/shops/{id}/reject', [App\Http\Controllers\AdminShopController::class, 'rejectShop']);

    // Paramètres Utilisateur
    Route::put('/user/profile', [App\Http\Controllers\ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [App\Http\Controllers\ProfileController::class, 'updatePassword']);
    Route::put('/user/notifications', [App\Http\Controllers\ProfileController::class, 'updateNotifications']);
});

// API Version 1 (Architecture Enterprise)
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
