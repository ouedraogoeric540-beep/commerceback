<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\StorageServiceInterface;

class TestSupabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Supabase Storage Service';

    /**
     * Execute the console command.
     */
    public function handle(StorageServiceInterface $storage)
    {
        $this->info('Testing Supabase Storage Service...');
        
        $bucket = 'product-images';
        $path = 'test-folder/test-file.txt';
        $content = 'Hello from Laravel!';

        // 1. Upload
        $this->info("1. Uploading to {$bucket}/{$path}...");
        $uploaded = $storage->upload($bucket, $path, $content, ['contentType' => 'text/plain']);
        if ($uploaded) {
            $this->info('✅ Upload successful.');
        } else {
            $this->error('❌ Upload failed.');
            return;
        }

        // 2. Exists
        $this->info("2. Checking existence...");
        $exists = $storage->exists($bucket, $path);
        if ($exists) {
            $this->info('✅ File exists.');
        } else {
            $this->error('❌ File not found.');
        }

        // 3. Public URL
        $this->info("3. Generating public URL...");
        $publicUrl = $storage->publicUrl($bucket, $path);
        $this->info("   URL: {$publicUrl}");

        // 4. Temporary URL
        $this->info("4. Generating temporary URL...");
        $tempUrl = $storage->temporaryUrl($bucket, $path, 60);
        $this->info("   URL: {$tempUrl}");

        // 5. Delete
        $this->info("5. Deleting file...");
        $deleted = $storage->delete($bucket, $path);
        if ($deleted) {
            $this->info('✅ Delete successful.');
        } else {
            $this->error('❌ Delete failed.');
        }
    }
}
