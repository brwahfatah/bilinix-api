<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'whmcs_client_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
            'whmcs_client_id'   => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Scoped helpers ────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}
