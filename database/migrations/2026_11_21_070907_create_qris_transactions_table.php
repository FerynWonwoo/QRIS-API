
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_transactions', function (Blueprint $table) {
            $table->id();

            // Foreign Key ke akun pembayar
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Foreign Key ke kode QRIS yang digunakan
            $table->foreignId('qris_code_id')
                ->constrained('qris_codes')
                ->restrictOnDelete();

            // Nomor referensi transaksi harus unik
            $table->string('reference_number', 50)->unique();

            // Nominal pembayaran
            $table->decimal('amount', 15, 2);

            // Status transaksi
            $table->enum('status', [
                'pending',
                'paid',
                'failed',
            ])->default('pending');

            $table->timestamps();

            // Index untuk mempercepat pencarian
            $table->index(['qris_code_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qris_transactions');
    }
};
