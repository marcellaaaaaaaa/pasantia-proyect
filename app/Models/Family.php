<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Family extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'property_id', 'name', 'is_active',
        'is_exonerated', 'exoneration_reason', 'exonerated_by', 'exonerated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_exonerated' => 'boolean',
        'exonerated_at' => 'datetime',
    ];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function people(): HasMany { return $this->hasMany(Person::class); }
    public function services(): BelongsToMany { return $this->belongsToMany(Service::class, 'family_service'); }
    public function exoneratedServices(): BelongsToMany { return $this->belongsToMany(Service::class, 'family_exonerated_service'); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function wallet(): MorphOne { return $this->morphOne(Wallet::class, 'owner'); }
    public function exoneratedBy(): BelongsTo { return $this->belongsTo(User::class, 'exonerated_by'); }

    /**
     * Una familia es solvente cuando no tiene facturas vencidas sin cobrar,
     * o cuando está exonerada por el administrador.
     */
    public function isSolvent(): bool
    {
        if ($this->is_exonerated) {
            return true;
        }

        return ! $this->invoices()
            ->whereIn('status', ['pending', 'approved', 'partial'])
            ->where('due_date', '<', today())
            ->exists();
    }
}
