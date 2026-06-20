<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2); // price in USD
            $table->string('cpu', 50);      // enough to store "4 vCPU"
            $table->string('ram', 50);      // enough to store "8GB"
            $table->string('storage', 50);  // enough to store "160GB SSD"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_plans');
    }
};
