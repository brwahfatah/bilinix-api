<?php

namespace App\DTO;

use Carbon\Carbon;

final class TicketReplyDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $message,
        public readonly string $author,
        public readonly string $createdAt,
    ) {}

    public static function fromWhmcs(array $data): self
    {
        return new self(
            id:        (string) ($data['id'] ?? ''),
            message:   $data['message'] ?? '',
            author:    $data['name'] ?? $data['requestor'] ?? 'Unknown',
            createdAt: self::parseDate($data['date'] ?? null) ?? now()->toIso8601String(),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'message'    => $this->message,
            'author'     => $this->author,
            'created_at' => $this->createdAt,
        ];
    }

    private static function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
