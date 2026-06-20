<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerPlan extends Model
{
    protected $fillable = ['name','price','cpu','ram','storage'];
}
