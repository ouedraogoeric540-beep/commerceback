<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DownloadToken;
use App\Services\DownloadService;
use App\Contracts\StorageServiceInterface;

class DownloadController extends Controller
{
    protected DownloadService $downloadService;
    protected StorageServiceInterface $storage;

    public function __construct(DownloadService $downloadService, StorageServiceInterface $storage)
    {
        $this->downloadService = $downloadService;
        $this->storage = $storage;
    }

    public function download(Request $request, $tokenStr)
    {
        $token = DownloadToken::where('token', $tokenStr)->with('orderItem.product')->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['message' => __('api.lien_de_t_l_chargement_invalid')], 403);
        }

        $product = $token->orderItem->product;

        if (!$product || !$product->digital_file || !$this->storage->exists('digital-products', $product->digital_file)) {
            return response()->json(['message' => __('api.le_fichier_est_introuvable_sur')], 404);
        }

        // Record the download
        $this->downloadService->recordDownload($token, $request->ip(), $request->userAgent());

        // Generate signed URL valid for 60 seconds
        $url = $this->storage->temporaryUrl('digital-products', $product->digital_file, 60);
        
        if (!$url) {
            return response()->json(['message' => __('api.erreur_lors_de_la_g_n_ration_d')], 500);
        }

        return redirect()->away($url);
    }
}
