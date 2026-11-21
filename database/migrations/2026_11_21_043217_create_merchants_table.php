
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel users
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Identitas merchant
            $table->string('merchant_name');
            $table->string('nmid', 50)->unique();
            $table->string('business_category', 100);
            $table->string('city', 100);

            // Status merchant
            $table->enum('status', ['active', 'inactive'])
                ->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
