<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = ['tenant_id', 'name', 'type', 'default_price_usd', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function isFixed(): bool { return $this->type === 'fixed'; }
    public function isJornada(): bool { return $this->type === 'jornada'; }

    public function families(): BelongsToMany { return $this->belongsToMany(Family::class, 'family_service'); }
}
