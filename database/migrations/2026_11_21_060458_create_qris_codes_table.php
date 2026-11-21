
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('merchant_id')
                ->constrained('merchants')
                ->restrictOnDelete();

            $table->string('reference_code', 80)->unique();
            $table->text('payload_string');

            $table->decimal('nominal', 15, 2);

            $table->enum('status', [
                'pending',
                'used',
                'expired',
            ])->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qris_codes');
    }
};
