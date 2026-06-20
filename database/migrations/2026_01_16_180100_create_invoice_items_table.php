
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
        $table->id();
$table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
$table->enum('type',['domain','server','renewal']);
$table->unsignedBigInteger('service_id')->nullable();
$table->string('description');
$table->decimal('amount',8,2);
$table->json('reference_data')->nullable();
$table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
