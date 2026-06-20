<?php

namespace App\DTO;

use App\Enums\VpsStatus;
use Carbon\Carbon;

final class VpsDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $label,
        public readonly string  $status,
        public readonly string  $cpu,
        public readonly string  $ram,
        public readonly string  $disk,
        public readonly string  $ip,
        public readonly ?string $location,
        public readonly ?string $expiresAt,
        public readonly string  $createdAt,
    ) {}

    public static function fromWhmcs(array $data): self
    {
        $status = VpsStatus::fromWhmcs($data['status'] ?? '');

        // Config options arrive as direct fields (configoption1, configoption2, …)
        $cpu  = (string) ($data['configoption1'] ?? 'N/A');
        $ram  = (string) ($data['configoption2'] ?? 'N/A');
        $disk = (string) ($data['configoption3'] ?? 'N/A');

        $expiresAt = self::parseDate($data['nextduedate'] ?? null);
        $createdAt = self::parseDate($data['regdate'] ?? null) ?? now()->toIso8601String();

        return new self(
            id:        (string) ($data['id'] ?? $data['serviceid'] ?? ''),
            label:     $data['domain'] ?? $data['domainalias'] ?? $data['name'] ?? 'Unnamed VPS',
            status:    $status->value,
            cpu:       $cpu,
            ram:       $ram,
            disk:      $disk,
            ip:        $data['dedicatedip'] ?? '',
            location:  $data['serverhostname'] ?? $data['server'] ?? null,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'status'     => $this->status,
            'cpu'        => $this->cpu,
            'ram'        => $this->ram,
            'disk'       => $this->disk,
            'ip'         => $this->ip,
            'location'   => $this->location,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
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
