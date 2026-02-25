<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inhabitant extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'family_id',
        'full_name',
        'cedula',
        'date_of_birth',
        'phone',
        'email',
        'is_primary_contact',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_primary_contact' => 'boolean',
        ];
    }

    // ─── Accessors ──────────────────────────────────────────────────────────────

    protected function age(): Attribute
    {
        return Attribute::get(
            fn () => $this->date_of_birth?->age
        );
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
