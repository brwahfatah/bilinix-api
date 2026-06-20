<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the legacy carts/cart_items tables that were created by earlier migrations
        // with an incompatible schema (cart_token, status, item_uid, etc.).
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::enableForeignKeyConstraints();

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 100)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('session_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
