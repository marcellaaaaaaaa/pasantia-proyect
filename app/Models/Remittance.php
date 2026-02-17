<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Remittance extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'collector_id',
        'reviewed_by',
        'amount_declared',
        'amount_confirmed',
        'status',
        'submitted_at',
        'reviewed_at',
        'collector_notes',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_declared'  => 'decimal:2',
            'amount_confirmed' => 'decimal:2',
            'submitted_at'     => 'datetime',
            'reviewed_at'      => 'datetime',
        ];
    }

    // ─── State machine ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /** Transiciona draft → submitted */
    public function submit(?string $notes = null): void
    {
        if (! $this->isDraft()) {
            throw new LogicException(
                "La remesa #{$this->id} tiene status '{$this->status}' y no puede ser enviada."
            );
        }

        $this->update([
            'status'          => 'submitted',
            'submitted_at'    => now(),
            'collector_notes' => $notes,
        ]);
    }

    /** Transiciona submitted → approved. Llamar solo desde RemittanceService::approve(). */
    public function markApproved(User $admin, float $amountConfirmed, ?string $notes = null): void
    {
        if (! $this->isSubmitted()) {
            throw new LogicException(
                "La remesa #{$this->id} tiene status '{$this->status}' y no puede ser aprobada."
            );
        }

        $this->update([
            'status'           => 'approved',
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
            'amount_confirmed' => $amountConfirmed,
            'admin_notes'      => $notes,
        ]);
    }

    /** Transiciona submitted → rejected. Llamar solo desde RemittanceService::reject(). */
    public function markRejected(User $admin, string $notes): void
    {
        if (! $this->isSubmitted()) {
            throw new LogicException(
                "La remesa #{$this->id} tiene status '{$this->status}' y no puede ser rechazada."
            );
        }

        $this->update([
            'status'      => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Pagos incluidos en esta remesa (pivot inmutable).
     * UNIQUE(payment_id) garantiza que un pago solo pertenece a una remesa.
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'remittance_payments')
                    ->withPivot('created_at');
    }

    public function vaultTransactions(): HasMany
    {
        return $this->hasMany(VaultTransaction::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /** Alias legible para remesas en espera de revisión */
    public function scopePendingReview($query)
    {
        return $query->where('status', 'submitted');
    }
}
