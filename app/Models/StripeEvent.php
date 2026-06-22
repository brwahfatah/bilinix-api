<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeEvent extends Model
{
    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['id', 'type', 'payload', 'processed_at'];

    protected $casts = ['payload' => 'array'];
}
