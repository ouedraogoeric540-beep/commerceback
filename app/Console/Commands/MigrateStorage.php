<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\KycDocument;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use App\Contracts\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MigrateStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:migrate-storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing local files to Supabase without deleting originals.';

    protected StorageServiceInterface $supabase;

    public function __construct(StorageServiceInterface $supabase)
    {
        parent::__construct();
        $this->supabase = $supabase;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting storage migration to Supabase...");

        $this->migrateShops();
        $this->migrateProducts();
        $this->migrateUsers();
        $this->migrateKyc();
        $this->migrateMessages();

        $this->info("Migration completed successfully.");
    }

    private function migrateFile($localPath, $newPath, $bucket, $disk = 'public')
    {
        if (!$localPath) return false;

        // Si le chemin ressemble déjà à un chemin migré (ex: contient shops/{id} ou users/{id}) on peut sauter 
        // ou si le localPath est une URL
        if (str_starts_with($localPath, 'http')) {
            return false;
        }

        if (!Storage::disk($disk)->exists($localPath)) {
            $this->warn("Local file not found: $localPath on disk $disk");
            return false;
        }

        try {
            $absolutePath = Storage::disk($disk)->path($localPath);
            $file = new UploadedFile($absolutePath, basename($absolutePath));
            
            $this->supabase->upload($bucket, $newPath, $file);
            $this->line("Migrated: $localPath -> $bucket/$newPath");
            return true;
        } catch (\Exception $e) {
            $this->error("Failed to migrate $localPath: " . $e->getMessage());
            return false;
        }
    }

    private function migrateShops()
    {
        $this->info("Migrating Shops...");
        $shops = Shop::all();
        foreach ($shops as $shop) {
            if ($shop->logo && !str_contains($shop->logo, 'shops/' . $shop->id . '/logos/')) {
                $ext = pathinfo($shop->logo, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "shops/{$shop->id}/logos/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($shop->logo, $newPath, 'public-assets')) {
                    $shop->logo = $newPath;
                    $shop->save();
                }
            }

            if ($shop->cover && !str_contains($shop->cover, 'shops/' . $shop->id . '/covers/')) {
                $ext = pathinfo($shop->cover, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "shops/{$shop->id}/covers/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($shop->cover, $newPath, 'public-assets')) {
                    $shop->cover = $newPath;
                    $shop->save();
                }
            }
        }
    }

    private function migrateProducts()
    {
        $this->info("Migrating Products...");
        $products = Product::all();
        foreach ($products as $product) {
            if ($product->cover_image && !str_contains($product->cover_image, 'products/' . $product->id . '/covers/')) {
                $ext = pathinfo($product->cover_image, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "products/{$product->id}/covers/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($product->cover_image, $newPath, 'product-images')) {
                    $product->cover_image = $newPath;
                    $product->save();
                }
            }

            if ($product->digital_file && !str_contains($product->digital_file, 'products/' . $product->id . '/files/')) {
                $ext = pathinfo($product->digital_file, PATHINFO_EXTENSION) ?: 'zip';
                $newPath = "products/{$product->id}/files/" . Str::random(10) . '.' . $ext;
                // previously digital_file was on local disk root, not public (see OrderController step 5)
                if ($this->migrateFile($product->digital_file, $newPath, 'digital-products', 'local')) {
                    $product->digital_file = $newPath;
                    $product->save();
                }
            }
        }
    }

    private function migrateUsers()
    {
        $this->info("Migrating Users...");
        $users = User::all();
        foreach ($users as $user) {
            if ($user->avatar && !str_contains($user->avatar, 'users/' . $user->id . '/avatars/')) {
                $ext = pathinfo($user->avatar, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "users/{$user->id}/avatars/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($user->avatar, $newPath, 'public-assets')) {
                    $user->avatar = $newPath;
                    $user->save();
                }
            }
        }
    }

    private function migrateKyc()
    {
        $this->info("Migrating KYC Documents...");
        $kycs = KycDocument::with('shop.user')->get();
        foreach ($kycs as $kyc) {
            if (!$kyc->shop || !$kyc->shop->user) continue;
            
            $userId = $kyc->shop->user->id;

            if ($kyc->document_recto && !str_contains($kyc->document_recto, 'users/' . $userId . '/kyc/')) {
                $ext = pathinfo($kyc->document_recto, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "users/{$userId}/kyc/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($kyc->document_recto, $newPath, 'user-files')) {
                    $kyc->document_recto = $newPath;
                    $kyc->save();
                }
            }

            if ($kyc->document_verso && !str_contains($kyc->document_verso, 'users/' . $userId . '/kyc/')) {
                $ext = pathinfo($kyc->document_verso, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "users/{$userId}/kyc/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($kyc->document_verso, $newPath, 'user-files')) {
                    $kyc->document_verso = $newPath;
                    $kyc->save();
                }
            }
        }
    }

    private function migrateMessages()
    {
        $this->info("Migrating Messages...");
        $messages = Message::whereNotNull('attachment_path')->get();
        foreach ($messages as $message) {
            if ($message->attachment_path && !str_contains($message->attachment_path, 'users/' . $message->sender_id . '/messages/')) {
                $ext = pathinfo($message->attachment_path, PATHINFO_EXTENSION) ?: 'jpg';
                $newPath = "users/{$message->sender_id}/messages/" . Str::random(10) . '.' . $ext;
                if ($this->migrateFile($message->attachment_path, $newPath, 'user-files')) {
                    $message->attachment_path = $newPath;
                    $message->save();
                }
            }
        }
    }
}
