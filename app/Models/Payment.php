<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'jornada_id',
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

    /** Jornada a la que pertenece este pago (opcional) */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    /** Registro del ledger de wallet generado por este pago */
    public function walletTransaction(): HasOne
    {
        return $this->hasOne(WalletTransaction::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Pagos no anulados (válidos para cálculos) */
    public function scopeValid($query)
    {
        return $query->where('status', '!=', 'reversed');
    }
}
