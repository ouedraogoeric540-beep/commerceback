<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Shop;
use App\Models\Product;
use App\Models\User;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test public catalog products list.
     */
    public function test_can_view_public_catalog()
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Demo Shop',
            'slug' => 'demo-shop',
            'status' => 'approved'
        ]);

        Product::create([
            'shop_id' => $shop->id,
            'title' => 'Sample Product',
            'slug' => 'sample-product',
            'price' => 500,
            'product_type' => 'physical',
            'is_active' => true
        ]);

        $response = $this->getJson('/api/public/catalog');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'products' => ['data']
                 ]);
    }
}
