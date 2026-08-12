<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DownloadToken;
use App\Services\DownloadService;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    protected DownloadService $downloadService;

    public function __construct(DownloadService $downloadService)
    {
        $this->downloadService = $downloadService;
    }

    public function download(Request $request, $tokenStr)
    {
        $token = DownloadToken::where('token', $tokenStr)->with('orderItem.product')->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['message' => __('api.lien_de_t_l_chargement_invalid')], 403);
        }

        $product = $token->orderItem->product;

        if (!$product || !$product->digital_file || !Storage::exists($product->digital_file)) {
            return response()->json(['message' => __('api.le_fichier_est_introuvable_sur')], 404);
        }

        // Record the download
        $this->downloadService->recordDownload($token, $request->ip(), $request->userAgent());

        $extension = pathinfo($product->digital_file, PATHINFO_EXTENSION);
        $downloadName = $product->slug . '.' . $extension;

        return Storage::download($product->digital_file, $downloadName);
    }
}
