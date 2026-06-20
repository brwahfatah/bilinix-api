<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'type', // 'domain' or 'server'
        'service_id', // id of the domain/server
        'description',
        'amount',
        'reference_data'
    ];

    protected $casts = [
        'reference_data' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Separate relations
    public function domainService()
    {
        return $this->belongsTo(Domain::class, 'service_id');
    }

    public function serverService()
    {
        return $this->belongsTo(Server::class, 'service_id');
    }

    // Helper to get the correct service
    public function service()
    {
        return $this->type === 'domain' ? $this->domainService : $this->serverService;
    }
}
