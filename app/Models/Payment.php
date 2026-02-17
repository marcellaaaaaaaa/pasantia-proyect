<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'billing_id',
        'collector_id',
        'amount',
        'payment_method',
        'status',
        'reference',
        'payment_date',
        'notes',
        'receipt_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'payment_date'    => 'date',
            'receipt_sent_at' => 'datetime',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    /** El cobrador que registró el pago */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** Registro del ledger de wallet generado por este pago */
    public function walletTransaction(): HasOne
    {
        return $this->hasOne(WalletTransaction::class);
    }

    /**
     * Remesa (liquidación) en la que fue incluido este pago.
     * UNIQUE(payment_id) garantiza que a lo sumo una remesa lo contiene.
     */
    public function remittances(): BelongsToMany
    {
        return $this->belongsToMany(Remittance::class, 'remittance_payments')
                    ->withPivot('created_at');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Pagos en la wallet del cobrador esperando ser liquidados */
    public function scopePendingRemittance($query)
    {
        return $query->where('status', 'pending_remittance');
    }

    /** Pagos ya conciliados con el vault */
    public function scopeConciliated($query)
    {
        return $query->where('status', 'conciliated');
    }

    /** Pagos no anulados (válidos para cálculos) */
    public function scopeValid($query)
    {
        return $query->where('status', '!=', 'reversed');
    }
}
