<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrisCode extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_id',
        'reference_code',
        'payload_string',
        'nominal',
        'status',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}