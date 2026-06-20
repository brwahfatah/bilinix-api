<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('domains', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('domain')->unique();
$table->enum('status',['pending','active','suspended','expired','transfer']);
$table->string('nameserver1');
$table->string('nameserver2');
$table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
$table->date('next_due_date')->nullable();
$table->timestamps();
$table->foreignId('server_id')->nullable();

    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
