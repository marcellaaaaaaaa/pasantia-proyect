<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
