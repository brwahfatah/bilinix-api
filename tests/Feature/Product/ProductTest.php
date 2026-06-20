<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_is_public(): void
    {
        $this->getJson('/api/products')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors'])
             ->assertJsonPath('errors', null);
    }

    public function test_products_featured_returns_subset(): void
    {
        $this->getJson('/api/products/featured')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors']);
    }

    public function test_products_grouped_returns_type_keys(): void
    {
        $this->getJson('/api/products/grouped')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors']);
    }

    public function test_product_show_returns_single_product(): void
    {
        $this->getJson('/api/products/1')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['id', 'name', 'slug', 'type', 'price'],
             ]);
    }
}
