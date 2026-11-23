<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QrisTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'qris_code_id',
        'reference_number',
        'amount',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qrisCode(): BelongsTo
    {
        return $this->belongsTo(QrisCode::class);
    }


    public function settlementTransaction(): HasOne
{
    return $this->hasOne(SettlementTransaction::class);
}
}