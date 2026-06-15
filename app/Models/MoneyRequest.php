<?php
// app/Models/MoneyRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyRequest extends Model
{
    protected $table = 'money_requests';
    
    protected $fillable = [
        'user_id',
        'wallet_id',
        'paid_by_user_id',
        'amount',
        'currency_code',
        'description',
        'status',
        'request_token',
        'expires_at',
        'paid_at',
    ];
    
    protected $casts = [
        'amount' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
    
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}