
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_transactions', function (Blueprint $table) {
            $table->id();

            // Relasi ke settlement
            $table->foreignId('settlement_id')
                ->constrained('settlements')
                ->restrictOnDelete();

            // Relasi ke transaksi QRIS
            $table->foreignId('qris_transaction_id')
                ->unique()
                ->constrained('qris_transactions')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_transactions');
    }
};
