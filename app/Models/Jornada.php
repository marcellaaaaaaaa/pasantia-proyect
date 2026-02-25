<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Jornada extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'collector_id',
        'status',
        'opened_at',
        'closed_at',
        'notes',
        'total_collected',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
            'total_collected' => 'decimal:2',
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Cierra la jornada, recalcula el total y guarda.
     *
     * @throws LogicException si la jornada ya está cerrada
     */
    public function close(?string $notes = null): void
    {
        if ($this->isClosed()) {
            throw new LogicException('La jornada ya está cerrada.');
        }

        $this->recalculateTotal();
        $this->status    = 'closed';
        $this->closed_at = now();

        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->save();
    }

    /**
     * Recalcula total_collected como la suma de pagos válidos (no reversed).
     */
    public function recalculateTotal(): void
    {
        $this->total_collected = $this->payments()
            ->where('status', '!=', 'reversed')
            ->sum('amount');

        $this->save();
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'jornada_sector');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'jornada_service');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByCollector($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }
}
