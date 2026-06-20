<?php

namespace App\DTO;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\Carbon;

final class TicketDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $subject,
        public readonly string  $department,
        public readonly string  $status,
        public readonly string  $priority,
        public readonly string  $createdAt,
        public readonly ?string $updatedAt,
        /** @var TicketReplyDTO[] */
        public readonly array   $replies,
    ) {}

    public static function fromWhmcs(array $data): self
    {
        $status   = TicketStatus::fromWhmcs($data['status'] ?? '');
        $priority = TicketPriority::fromWhmcs($data['priority'] ?? '');

        // GetTicket uses 'ticketid'; GetTickets list items use 'id'
        $id = (string) ($data['ticketid'] ?? $data['id'] ?? '');

        return new self(
            id:         $id,
            subject:    $data['subject'] ?? '',
            department: $data['deptname'] ?? 'General',
            status:     $status->value,
            priority:   $priority->value,
            createdAt:  self::parseDate($data['date'] ?? null) ?? now()->toIso8601String(),
            updatedAt:  self::parseDate($data['lastreply'] ?? null),
            replies:    self::parseReplies($data['replies'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'subject'    => $this->subject,
            'department' => $this->department,
            'status'     => $this->status,
            'priority'   => $this->priority,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'replies'    => array_map(fn($r) => $r->toArray(), $this->replies),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Normalize the WHMCS replies structure.
     * GetTicket returns replies.reply as either a single object or a list.
     * GetTickets list items have no replies key at all.
     *
     * @return TicketReplyDTO[]
     */
    private static function parseReplies(mixed $replies): array
    {
        if (empty($replies)) {
            return [];
        }

        // Unwrap the replies.reply nesting from GetTicket
        $raw = is_array($replies) && isset($replies['reply']) ? $replies['reply'] : $replies;

        if (empty($raw) || ! is_array($raw)) {
            return [];
        }

        // Single reply comes as an associative array (not a sequential list)
        $list = array_is_list($raw) ? $raw : [$raw];

        return array_values(array_map(
            fn($r) => TicketReplyDTO::fromWhmcs($r),
            array_filter($list, 'is_array'),
        ));
    }

    private static function parseDate(?string $date): ?string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
