<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_id',
        'server_plan_id',
        'name',
        'os',            // ✅ Add this
        'provider',
        'config',
        'period',
        'status',
        'ip_address',
        'ssh_username',
        'ssh_password',
        'ssh_key',
        'next_due_date',
        'approved_at',
        'approved_by',
        'provisioned_at',
        'activated_at',
        'last_error',
    ];

    protected $casts = [
        'config' => 'array',
        'next_due_date' => 'date',
        'approved_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /* =====================
     * RELATIONSHIPS
     * ===================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan()
    {
        return $this->belongsTo(ServerPlan::class);
    }

    /* =====================
     * STATE HELPERS
     * ===================== */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isProvisioning(): bool
    {
        return $this->status === 'provisioning';
    }
}
