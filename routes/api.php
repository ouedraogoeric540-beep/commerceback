<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Homepage dynamique
Route::get('/public/homepage', [\App\Http\Controllers\HomepageController::class, 'index']);

// Vitrines publiques
Route::get('/public/catalog', [App\Http\Controllers\PublicController::class, 'catalog']);
Route::get('/public/shops/{slug}', [App\Http\Controllers\PublicController::class, 'getShop']);
Route::get('/public/products/{id}', [App\Http\Controllers\PublicController::class, 'getProduct']);
Route::get('/public/funnel/{shopSlug}/{productSlug}', [\App\Http\Controllers\FunnelController::class, 'getFunnelData']);
Route::post('/public/shops/onboard', [App\Http\Controllers\ShopController::class, 'onboardGuest']);


// Téléchargements Sécurisés (Tokens)
Route::get('/downloads/{token}', [App\Http\Controllers\DownloadController::class, 'download']);

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // Route supprimée ou corrigée car AdminController n'existe pas

    // Admin - Dashboard (Vue d'ensemble)
    Route::get('/admin/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'getStats']);

    // Admin - Litiges


    // Admin - Paramètres système
    Route::get('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index']);
    Route::put('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'update']);

    // Admin - Journal d'audit
    Route::get('/admin/logs', [\App\Http\Controllers\AdminAuditLogController::class, 'index']);
    Route::get('/admin/logs/actions', [\App\Http\Controllers\AdminAuditLogController::class, 'actions']);
    
    Route::get('/user', [AuthController::class, 'me']);
    
    // Paramètres de la boutique
    Route::put('/vendor/shop/settings', [ShopController::class, 'updateSettings']);


    // Gestion des produits
    Route::post('/vendor/products/reorder', [App\Http\Controllers\ProductController::class, 'reorderProducts']);
    
    // Boutiques
    Route::post('/shops', [ShopController::class, 'store']);
    Route::get('/shops/check-slug', [ShopController::class, 'checkSlug']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']); // Pour les vendeurs
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    
    // Boutique du vendeur (Gestion)
    Route::get('/my-shop', [ShopController::class, 'myShop']);
    Route::post('/my-shop/update', [ShopController::class, 'update']);
    Route::put('/vendor/shop/settings', [ShopController::class, 'updateSettings']);

    // Commandes du vendeur
    Route::get('/seller/stats', [App\Http\Controllers\OrderController::class, 'getSellerStats']);
    Route::get('/seller/orders', [App\Http\Controllers\OrderController::class, 'getSellerOrders']);



    // Espace Acheteur (commandes + téléchargements + litiges)
    Route::get('/buyer/stats', [App\Http\Controllers\OrderController::class, 'getBuyerStats']);
    Route::get('/buyer/orders', [App\Http\Controllers\OrderController::class, 'getBuyerOrders']);
    Route::get('/buyer/orders/{orderId}/download/{productId}', [App\Http\Controllers\OrderController::class, 'downloadDigitalProduct']);
    Route::get('/buyer/downloads', [\App\Http\Controllers\BuyerController::class, 'myDownloads']);


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
    Route::get('/admin/kyc/pending', [App\Http\Controllers\AdminKycController::class, 'pendingShops']);
    Route::get('/admin/kyc/history', [App\Http\Controllers\AdminKycController::class, 'history']);
    Route::post('/admin/kyc/{id}/approve', [App\Http\Controllers\AdminKycController::class, 'approve']);
    Route::post('/admin/kyc/{id}/reject', [App\Http\Controllers\AdminKycController::class, 'reject']);
    Route::get('/admin/kyc/{id}/document/{side}', [App\Http\Controllers\AdminKycController::class, 'viewDocument']);

    // Administration (Utilisateurs)
    Route::get('/admin/users', [App\Http\Controllers\AdminUserController::class, 'index']);
    Route::post('/admin/users/{id}/toggle-block', [App\Http\Controllers\AdminUserController::class, 'toggleBlock']);

    Route::post('/admin/users/{id}/roles', [App\Http\Controllers\AdminUserController::class, 'syncRoles']);

    // Administration (Catalogue & Modération)
    Route::get('/admin/categories', [App\Http\Controllers\AdminCatalogController::class, 'getCategories']);
    Route::post('/admin/categories', [App\Http\Controllers\AdminCatalogController::class, 'storeCategory']);
    Route::put('/admin/categories/{id}', [App\Http\Controllers\AdminCatalogController::class, 'updateCategory']);
    
    Route::get('/admin/products', [App\Http\Controllers\AdminCatalogController::class, 'getProducts']);
    Route::post('/admin/products/{id}/suspend', [App\Http\Controllers\AdminCatalogController::class, 'toggleProductSuspension']);
    
    // Paramètres Utilisateur
    Route::put('/user/profile', [App\Http\Controllers\ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [App\Http\Controllers\ProfileController::class, 'updatePassword']);
    Route::put('/user/notifications', [App\Http\Controllers\ProfileController::class, 'updateNotifications']);

    // ─── Messagerie ──────────────────────────────────────────────────────────
    Route::get('/messages/unread-count', [App\Http\Controllers\MessageController::class, 'unreadCount']);
    Route::get('/conversations', [App\Http\Controllers\MessageController::class, 'index']);
    Route::post('/conversations', [App\Http\Controllers\MessageController::class, 'findOrCreate']);
    Route::get('/conversations/{id}/messages', [App\Http\Controllers\MessageController::class, 'messages']);
    Route::post('/conversations/{id}/messages', [App\Http\Controllers\MessageController::class, 'send']);
    Route::get('/messages/attachment/{id}', [App\Http\Controllers\MessageController::class, 'downloadAttachment']);
});

// API Version 1 (Architecture Enterprise)
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
