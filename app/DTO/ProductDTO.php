<?php

namespace App\DTO;

use App\Enums\ProductType;
use Illuminate\Support\Str;

final class ProductDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly string $type,
        public readonly string $price,
        public readonly string $billingCycle,
        public readonly bool   $featured,
        public readonly array  $specifications,
        public readonly bool   $available,
    ) {}

    // ── Factory: WHMCS product (GetProducts) ──────────────────────────────────

    public static function fromWhmcs(array $data): self
    {
        $type = ProductType::fromWhmcs(
            $data['type']   ?? 'other',
            $data['name']   ?? '',
            $data['module'] ?? '',
        );

        // WHMCS pricing is keyed by currency code; take the first (usually USD)
        $currencyPricing = ! empty($data['pricing']) ? reset($data['pricing']) : [];
        [$price, $cycle] = self::cheapestCycle($currencyPricing);

        $stockControl = (int) ($data['stockcontrol'] ?? 0);
        $stockLevel   = (int) ($data['stocklevel']   ?? 0);

        return new self(
            id:             (string) ($data['pid'] ?? ''),
            name:           $data['name'] ?? '',
            slug:           Str::slug($data['name'] ?? ''),
            description:    strip_tags($data['description'] ?? ''),
            type:           $type->value,
            price:          $price,
            billingCycle:   $cycle,
            featured:       self::detectFeatured($data['customfields'] ?? []),
            specifications: ['pricing' => self::normalizePricing($currencyPricing)],
            available:      ! ($stockControl === 1 && $stockLevel === 0),
        );
    }

    // ── Factory: WHMCS TLD pricing (GetTLDPricing) ────────────────────────────

    public static function fromDomainPricing(string $tld, array $pricing): self
    {
        $registerPrice = self::firstPositive($pricing['register'] ?? []);

        // Mark the most common TLDs as featured
        $popular = ['.com', '.net', '.org', '.io', '.co'];

        return new self(
            id:             'domain-' . ltrim($tld, '.'),
            name:           strtoupper(ltrim($tld, '.')) . ' Domain',
            slug:           'domain-' . strtolower(ltrim($tld, '.')),
            description:    "Register a {$tld} domain name.",
            type:           ProductType::Domain->value,
            price:          $registerPrice,
            billingCycle:   'annually',
            featured:       in_array(strtolower($tld), $popular, true),
            specifications: [
                'tld'      => $tld,
                'register' => $pricing['register'] ?? [],
                'renew'    => $pricing['renew']    ?? [],
                'transfer' => $pricing['transfer'] ?? [],
            ],
            available:      $registerPrice !== '0.00',
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'type'           => $this->type,
            'price'          => $this->price,
            'billing_cycle'  => $this->billingCycle,
            'featured'       => $this->featured,
            'specifications' => $this->specifications,
            'available'      => $this->available,
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Return the first (shortest) billing cycle with a positive price.
     * WHMCS uses -1.00 to mark a cycle as disabled.
     *
     * @return array{0: string, 1: string} [price, cycle]
     */
    private static function cheapestCycle(array $pricing): array
    {
        foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $cycle) {
            $price = (float) ($pricing[$cycle] ?? -1);
            if ($price > 0) {
                return [number_format($price, 2, '.', ''), $cycle];
            }
        }

        return ['0.00', 'monthly'];
    }

    /**
     * Build a clean pricing map, excluding disabled cycles (-1.00).
     */
    private static function normalizePricing(array $pricing): array
    {
        $result = [];
        foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $cycle) {
            $price = (float) ($pricing[$cycle] ?? -1);
            if ($price >= 0) {
                $result[$cycle] = number_format($price, 2, '.', '');
            }
        }
        return $result;
    }

    /**
     * Return the first positive price string from a year-keyed pricing array.
     */
    private static function firstPositive(array $prices): string
    {
        foreach ($prices as $price) {
            $f = (float) $price;
            if ($f > 0) {
                return number_format($f, 2, '.', '');
            }
        }
        return '0.00';
    }

    /**
     * Check WHMCS custom field "featured" = "yes".
     */
    private static function detectFeatured(mixed $customFields): bool
    {
        $fields = $customFields['customfield'] ?? $customFields;

        if (! is_array($fields)) {
            return false;
        }

        // Single custom field comes as an associative array, not a list
        $list = array_is_list($fields) ? $fields : [$fields];

        foreach ($list as $field) {
            if (is_array($field)
                && strtolower($field['name'] ?? '') === 'featured'
                && strtolower($field['value'] ?? '') === 'yes') {
                return true;
            }
        }

        return false;
    }
}
