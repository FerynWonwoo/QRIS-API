
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {

            // Relasi ke akun pembayar
            $table->foreignId('user_id')
                ->after('id')
                ->constrained('users')
                ->restrictOnDelete();

            // Relasi ke kode QRIS
            $table->foreignId('qris_code_id')
                ->after('user_id')
                ->constrained('qris_codes')
                ->restrictOnDelete();

            // Nomor referensi unik
            $table->string('reference_number', 50)
                ->unique()
                ->after('qris_code_id');

            // Nominal transaksi
            $table->decimal('amount', 15, 2)
                ->after('reference_number');

            // Status pembayaran
            $table->enum('status', [
                'pending',
                'paid',
                'failed',
            ])
                ->default('pending')
                ->after('amount');

            $table->index(['qris_code_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropIndex(['qris_code_id', 'status']);

            $table->dropForeign(['qris_code_id']);
            $table->dropForeign(['user_id']);

            $table->dropColumn([
                'user_id',
                'qris_code_id',
                'reference_number',
                'amount',
                'status',
            ]);
        });
    }
};
