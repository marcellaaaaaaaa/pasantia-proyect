<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Collection extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'invoice_id', 'collector_id', 'amount', 'currency', 
        'exchange_rate', 'amount_usd', 'method', 'reference', 'collected_at', 'status', 'notes'
    ];
    protected $casts = ['collected_at' => 'date', 'amount_usd' => 'decimal:2', 'exchange_rate' => 'decimal:4'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function collector(): BelongsTo { return $this->belongsTo(User::class, 'collector_id'); }
    public function walletTransactions(): MorphMany { return $this->morphMany(WalletTransaction::class, 'source'); }
}
