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
  Schema::create('cart_items', function (Blueprint $table) {
    $table->id(); // DB PK (auto increment)

    $table->foreignId('cart_id')->constrained()->cascadeOnDelete();

    $table->string('item_uid')->index(); // 👈 frontend ID (vps-2-zana)

    $table->enum('type', ['domain', 'server']);
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->integer('quantity');
    $table->integer('period');
    $table->string('period_label')->nullable();
    $table->json('meta')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
