<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $table = 'merchants';

    protected $fillable = [
        'user_id',
        'merchant_name',
        'nmid',
        'business_category',
        'city',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function qrisCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(QrisCode::class);
}

    public function settlements(): HasMany
{
    return $this->hasMany(Settlement::class);
}



}
