<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
   $table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
$table->decimal('amount',8,2)->default(0);
$table->string('currency',3)->default('USD');
$table->enum('status',['unpaid','paid','cancelled'])->default('unpaid');
$table->timestamp('paid_at')->nullable();
$table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
