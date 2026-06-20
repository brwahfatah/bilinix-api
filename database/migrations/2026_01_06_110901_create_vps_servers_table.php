<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_plan_id')->constrained();

            $table->string('name');
            $table->string('os'); // required by ServiceLifecycleManager
            $table->unsignedInteger('period')->default(1); // billing period in months

            $table->string('provider')->default('local');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->string('ssh_username')->nullable();
            $table->string('ssh_password')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->json('config')->nullable(); // extra config (os, location, ipv6)
            $table->string('ip_address')->nullable();

            $table->enum('status', [
                'pending',
                'awaiting_approval',
                'provisioning',
                'active',
                'suspended',
                'terminated',
                'failed'
            ])->default('pending');

            $table->text('ssh_key')->nullable();
            $table->date('next_due_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
