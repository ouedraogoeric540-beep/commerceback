<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    /**
     * Upload a file to a specific bucket.
     *
     * @param string $bucket The target bucket name.
     * @param string $path The destination path within the bucket (e.g., 'products/123/covers/image.jpg').
     * @param UploadedFile|string|resource $file The file to upload.
     * @param array $options Additional options (e.g., contentType).
     * @return bool True on success, False on failure.
     */
    public function upload(string $bucket, string $path, $file, array $options = []): bool;

    /**
     * Delete a file from a specific bucket.
     *
     * @param string $bucket The bucket name.
     * @param string $path The path of the file to delete.
     * @return bool True on success, False on failure.
     */
    public function delete(string $bucket, string $path): bool;

    /**
     * Check if a file exists in a specific bucket.
     *
     * @param string $bucket The bucket name.
     * @param string $path The path of the file.
     * @return bool True if exists, False otherwise.
     */
    public function exists(string $bucket, string $path): bool;

    /**
     * Get the public URL for a file in a public bucket.
     *
     * @param string $bucket The bucket name.
     * @param string $path The path of the file.
     * @return string The public URL.
     */
    public function publicUrl(string $bucket, string $path): string;

    /**
     * Get a temporary signed URL for a file in a private bucket.
     *
     * @param string $bucket The bucket name.
     * @param string $path The path of the file.
     * @param int $expiresIn Expiration time in seconds (e.g., 60).
     * @return string The signed URL.
     */
    public function temporaryUrl(string $bucket, string $path, int $expiresIn = 60): string;

    /**
     * Download the file contents.
     *
     * @param string $bucket The bucket name.
     * @param string $path The path of the file.
     * @return string|null The file content or null on failure.
     */
    public function get(string $bucket, string $path): ?string;
}
