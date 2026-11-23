
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // Admin yang memproses settlement
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Merchant penerima dana
            $table->foreignId('merchant_id')
                ->constrained('merchants')
                ->restrictOnDelete();

            // Identitas batch pencairan
            $table->string('batch_code', 80)->unique();

            // Total dana yang dicairkan
            $table->decimal('total_amount', 15, 2);

            // Informasi rekening tujuan
            $table->string('bank_name', 100);
            $table->string('account_number', 50);

            // Status settlement
            $table->enum('status', [
                'pending',
                'processed',
                'completed',
                'failed',
            ])->default('pending');

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
