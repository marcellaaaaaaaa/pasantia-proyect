<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Billing extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'family_id',
        'service_id',
        'period',
        'amount',
        'status',
        'due_date',
        'notes',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'due_date'     => 'date',
            'generated_at' => 'datetime',
        ];
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Filtra por período: byPeriod('2026-02') */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /** Solo deudas sin pagar (pending o partial) */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    /** Solo deudas completamente pagadas */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // ─── Accessors ─────────────────────────────────────────────────────────────

    /** Suma de pagos no anulados registrados para este billing */
    public function getAmountPaidAttribute(): float
    {
        return (float) $this->payments()
            ->whereNotIn('status', ['reversed'])
            ->sum('amount');
    }

    /** Monto que aún falta por pagar */
    public function getAmountPendingAttribute(): float
    {
        return max(0, (float) $this->amount - $this->amount_paid);
    }
}
