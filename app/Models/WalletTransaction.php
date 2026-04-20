<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = ['tenant_id', 'wallet_id', 'type', 'amount_usd', 'description', 'source_id', 'source_type'];

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function source(): MorphTo { return $this->morphTo(); }
}
