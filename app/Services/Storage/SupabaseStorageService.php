<?php

namespace App\Services\Storage;

use App\Contracts\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService implements StorageServiceInterface
{
    protected string $url;
    protected string $key;

    public function __construct()
    {
        $this->url = rtrim(config('services.supabase.url'), '/');
        $this->key = config('services.supabase.key');
    }

    /**
     * Get the base storage API URL
     */
    protected function getStorageUrl(): string
    {
        return $this->url . '/storage/v1';
    }

    /**
     * Get default headers for Supabase API requests
     */
    protected function getHeaders(): array
    {
        return [
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
        ];
    }

    public function upload(string $bucket, string $path, $file, array $options = []): bool
    {
        $endpoint = $this->getStorageUrl() . "/object/{$bucket}/" . ltrim($path, '/');

        // Check if file is UploadedFile instance
        if ($file instanceof UploadedFile) {
            $content = file_get_contents($file->getRealPath());
            $contentType = $file->getMimeType() ?? 'application/octet-stream';
        } else if (is_string($file) && is_file($file)) {
            $content = file_get_contents($file);
            $contentType = mime_content_type($file) ?: 'application/octet-stream';
        } else {
            $content = $file;
            $contentType = $options['contentType'] ?? 'application/octet-stream';
        }

        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->withHeaders([
                'Content-Type' => $contentType,
            ])
            ->withBody($content, $contentType)
            ->post($endpoint);

        if (!$response->successful()) {
            Log::error("Supabase Upload Failed", [
                'bucket' => $bucket,
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            return false;
        }

        return true;
    }

    public function delete(string $bucket, string $path): bool
    {
        $endpoint = $this->getStorageUrl() . "/object/{$bucket}/" . ltrim($path, '/');

        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->delete($endpoint);

        if (!$response->successful()) {
            Log::error("Supabase Delete Failed", [
                'bucket' => $bucket,
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            return false;
        }

        return true;
    }

    public function exists(string $bucket, string $path): bool
    {
        $endpoint = $this->getStorageUrl() . "/object/info/{$bucket}/" . ltrim($path, '/');
        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->get($endpoint);

        return $response->successful();
    }

    public function publicUrl(string $bucket, string $path): string
    {
        return $this->getStorageUrl() . "/object/public/{$bucket}/" . ltrim($path, '/');
    }

    public function temporaryUrl(string $bucket, string $path, int $expiresIn = 60): string
    {
        $endpoint = $this->getStorageUrl() . "/object/sign/{$bucket}/" . ltrim($path, '/');

        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->post($endpoint, [
                'expiresIn' => $expiresIn
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['signedURL'])) {
                // Return full URL
                return $this->getStorageUrl() . $data['signedURL'];
            }
        }

        Log::error("Supabase Temporary URL Failed", [
            'bucket' => $bucket,
            'path' => $path,
            'status' => $response->status(),
            'response' => $response->json()
        ]);
        
        return '';
    }

    public function get(string $bucket, string $path): ?string
    {
        $endpoint = $this->getStorageUrl() . "/object/{$bucket}/" . ltrim($path, '/');

        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->get($endpoint);

        if ($response->successful()) {
            return $response->body();
        }

        return null;
    }
}
