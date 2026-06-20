<?php

namespace App\Services;

use App\DTO\ProductDTO;
use App\Integrations\WhmcsService;
use RuntimeException;

class ProductService
{
    public function __construct(private readonly WhmcsService $whmcs) {}

    /**
     * Return all products: WHMCS products + domain TLD pricing.
     *
     * @return ProductDTO[]
     */
    public function list(): array
    {
        $products = array_map(
            fn(array $p) => ProductDTO::fromWhmcs($p),
            $this->whmcs->getProducts()
        );

        // Domain pricing is a bonus — never fail the entire list if WHMCS errors
        try {
            $domainPricing = $this->whmcs->getDomainPricing();
            foreach ($domainPricing as $tld => $pricing) {
                if (is_string($tld) && is_array($pricing)) {
                    $products[] = ProductDTO::fromDomainPricing($tld, $pricing);
                }
            }
        } catch (\Throwable) {
            // Non-fatal: domain pricing optional
        }

        return $products;
    }

    /**
     * Return a single WHMCS product by numeric ID.
     */
    public function get(int $id): ProductDTO
    {
        $raw = $this->whmcs->getProduct($id);
        return ProductDTO::fromWhmcs($raw);
    }

    /**
     * Return only featured products (WHMCS custom field "featured" = "yes",
     * or the popular domain TLDs).
     *
     * @return ProductDTO[]
     */
    public function featured(): array
    {
        return array_values(
            array_filter($this->list(), fn(ProductDTO $p) => $p->featured)
        );
    }

    /**
     * Return all products grouped by type.
     *
     * @return array<string, array<int, array>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->list() as $product) {
            $grouped[$product->type][] = $product->toArray();
        }

        return $grouped;
    }
}
