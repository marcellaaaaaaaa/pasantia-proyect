<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionRound extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'collector_id', 'name', 'status', 'billing_period',
        'opened_at', 'closed_at', 'total_collected_usd', 'notes'
    ];
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function collector(): BelongsTo { return $this->belongsTo(User::class, 'collector_id'); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function sectors(): BelongsToMany { return $this->belongsToMany(Sector::class, 'collection_round_sector'); }
    public function services(): BelongsToMany { return $this->belongsToMany(Service::class, 'collection_round_service'); }
}
