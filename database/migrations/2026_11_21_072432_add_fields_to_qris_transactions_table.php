
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom sudah dibuat pada migration
        // create_qris_transactions_table.
    }

    public function down(): void
    {
        // Tidak menghapus kolom karena sudah dimiliki
        // migration utama.
    }
};
