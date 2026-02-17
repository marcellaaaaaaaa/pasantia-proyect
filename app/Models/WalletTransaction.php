<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletTransaction extends Model
{
    /**
     * Ledger inmutable: solo created_at, sin updated_at.
     * La columna updated_at no existe en la tabla.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'payment_id',
        'remittance_id',
        'type',
        'amount',
        'balance_after',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at'    => 'datetime',
        ];
    }

    // ─── Inmutabilidad ─────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Prevenir modificaciones accidentales al ledger
        static::updating(function (): never {
            throw new LogicException(
                'WalletTransaction es inmutable: los registros del ledger no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'WalletTransaction es inmutable: los registros del ledger no pueden eliminarse.'
            );
        });
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // remittance() se añadirá cuando exista el modelo Remittance (MOD-010)
}
