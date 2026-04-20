<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = ['tenant_id', 'owner_id', 'owner_type', 'balance_usd', 'currency'];

    public function owner(): MorphTo { return $this->morphTo(); }
    public function transactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
}
