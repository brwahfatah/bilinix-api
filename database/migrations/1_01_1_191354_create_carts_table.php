<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
 Schema::create('carts', function (Blueprint $table) {
    $table->id();
    $table->uuid('cart_token')->unique(); // ⭐ REQUIRED
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('status', ['open', 'locked', 'checked_out'])->default('open');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
