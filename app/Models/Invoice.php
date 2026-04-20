<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'family_id', 'service_id', 'collection_round_id', 
        'description', 'amount_usd', 'collected_amount_usd', 'status', 'due_date'
    ];
    protected $casts = ['due_date' => 'date', 'amount_usd' => 'decimal:2', 'collected_amount_usd' => 'decimal:2'];

    public function family(): BelongsTo { return $this->belongsTo(Family::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function collectionRound(): BelongsTo { return $this->belongsTo(CollectionRound::class); }
    public function collections(): HasMany { return $this->hasMany(Collection::class); }

    public function getBalanceUsdAttribute(): float { return max(0, (float)$this->amount_usd - (float)$this->collected_amount_usd); }
}
