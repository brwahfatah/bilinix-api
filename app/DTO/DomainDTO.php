<?php

namespace App\DTO;

use App\Enums\DomainStatus;
use Carbon\Carbon;

final class DomainDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $domain,
        public readonly string  $status,
        public readonly ?string $expiryDate,
        public readonly bool    $autoRenew,
        public readonly bool    $locked,
        public readonly bool    $idProtection,
        public readonly array   $nameservers,
        public readonly ?array  $registrant,
        public readonly string  $createdAt,
    ) {}

    public static function fromWhmcs(array $data): self
    {
        $status = DomainStatus::fromWhmcs($data['status'] ?? '');

        // Collect populated nameserver slots (ns1–ns5)
        $nameservers = [];
        for ($i = 1; $i <= 5; $i++) {
            $ns = trim((string) ($data["nameserver{$i}"] ?? ''));
            if ($ns !== '') {
                $nameservers[] = $ns;
            }
        }

        $expiryDate = self::parseDate($data['nextduedate'] ?? $data['expirydate'] ?? null);
        $createdAt  = self::parseDate($data['registrationdate'] ?? $data['regdate'] ?? null)
                      ?? now()->toIso8601String();

        // Registrant contact — present only when WHMCS includes it in the response
        $registrant = null;
        if (! empty($data['registrant']) && is_array($data['registrant'])) {
            $r = $data['registrant'];
            $registrant = [
                'name'    => trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? '')),
                'email'   => $r['email'] ?? '',
                'country' => $r['country'] ?? '',
            ];
        }

        return new self(
            id:           (string) ($data['id'] ?? ''),
            domain:       $data['domainname'] ?? $data['domain'] ?? '',
            status:       $status->value,
            expiryDate:   $expiryDate,
            autoRenew:    (bool) ($data['autorenew'] ?? false),
            locked:       (bool) ($data['locked'] ?? false),
            idProtection: (bool) ($data['idprotection'] ?? false),
            nameservers:  $nameservers,
            registrant:   $registrant,
            createdAt:    $createdAt,
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'domain'       => $this->domain,
            'status'       => $this->status,
            'expiry_date'  => $this->expiryDate,
            'auto_renew'   => $this->autoRenew,
            'locked'       => $this->locked,
            'id_protection'=> $this->idProtection,
            'nameservers'  => $this->nameservers,
            'registrant'   => $this->registrant,
            'created_at'   => $this->createdAt,
        ];
    }

    private static function parseDate(?string $date): ?string
    {
        if (empty($date) || $date === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
