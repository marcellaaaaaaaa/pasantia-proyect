<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Sector extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function families(): HasManyThrough
    {
        return $this->hasManyThrough(Family::class, Property::class);
    }

    /** Cobradores asignados a esta calle */
    public function collectors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sector_user')
            ->withPivot('assigned_at');
    }

    public function jornadas(): BelongsToMany
    {
        return $this->belongsToMany(Jornada::class, 'jornada_sector');
    }
}
