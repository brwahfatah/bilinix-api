<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'amount',
        'currency',
        'status',
        'paid_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime'
    ];

    public function user()
{
    return $this->belongsTo(\App\Models\User::class);
}

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function cart()
    {
        return $this->belongsTo(\App\Models\Cart::class);
    }

    public function recalculateTotal()
    {
        $this->update([
            'amount' => $this->items()->sum('amount')
        ]);
    }
}


