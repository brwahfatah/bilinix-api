<?php

namespace App\DTO;

use App\Models\User;

final class UserDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $createdAt,
    ) {}

    public static function from(User $user): self
    {
        return new self(
            id:        $user->id,
            name:      $user->name,
            email:     $user->email,
            role:      $user->role ?? 'client',
            createdAt: $user->created_at->toIso8601String(),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'created_at' => $this->createdAt,
        ];
    }
}
