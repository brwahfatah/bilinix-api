<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        $list = $this->products->list();

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Products retrieved'
        );
    }

    /**
     * GET /api/products/featured
     * Must be registered BEFORE /api/products/{id} to avoid wildcard clash.
     */
    public function featured(Request $request): JsonResponse
    {
        $list = $this->products->featured();

        return $this->success(
            array_map(fn($dto) => $dto->toArray(), $list),
            'Featured products retrieved'
        );
    }

    /**
     * GET /api/products/grouped
     * Must be registered BEFORE /api/products/{id} to avoid wildcard clash.
     */
    public function grouped(Request $request): JsonResponse
    {
        return $this->success(
            $this->products->grouped(),
            'Products grouped by type'
        );
    }

    /**
     * GET /api/products/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $product = $this->products->get($id);
            return $this->success($product->toArray(), 'Product retrieved');
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }
}
