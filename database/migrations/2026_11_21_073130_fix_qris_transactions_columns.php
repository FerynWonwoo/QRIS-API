
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {

            // Foreign Key ke akun pembayar
            if (!Schema::hasColumn('qris_transactions', 'user_id')) {
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete();
            }

            // Foreign Key ke kode QRIS
            if (!Schema::hasColumn('qris_transactions', 'qris_code_id')) {
                $table->foreignId('qris_code_id')
                    ->constrained('qris_codes')
                    ->restrictOnDelete();
            }

            // Nomor referensi transaksi unik
            if (!Schema::hasColumn('qris_transactions', 'reference_number')) {
                $table->string('reference_number', 50)->unique();
            }

            // Nominal transaksi
            if (!Schema::hasColumn('qris_transactions', 'amount')) {
                $table->decimal('amount', 15, 2);
            }

            // Status pembayaran
            if (!Schema::hasColumn('qris_transactions', 'status')) {
                $table->enum('status', [
                    'pending',
                    'paid',
                    'failed',
                ])->default('pending');
            }
        });
    }

    public function down(): void
    {
        // Tidak menghapus kolom karena mungkin sudah digunakan
        // oleh migration atau fitur transaksi lainnya.
    }
};
