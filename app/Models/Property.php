<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = ['tenant_id', 'sector_id', 'address', 'type', 'unit_number'];

    public function sector(): BelongsTo { return $this->belongsTo(Sector::class); }
    public function families(): HasMany { return $this->hasMany(Family::class); }
}
