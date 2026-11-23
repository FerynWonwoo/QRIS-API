<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementTransaction extends Model
{
    protected $fillable = [
        'settlement_id',
        'qris_transaction_id',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function qrisTransaction(): BelongsTo
    {
        return $this->belongsTo(QrisTransaction::class);
    }
}
