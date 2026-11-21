<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrisTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'qris_code_id',
        'reference_number',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relasi ke akun pembayar
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke kode QRIS
    public function qrisCode(): BelongsTo
    {
        return $this->belongsTo(QrisCode::class);
    }
}
