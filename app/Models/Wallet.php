<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    // ─── Factory ───────────────────────────────────────────────────────────────

    /**
     * Obtiene la wallet de un cobrador o la crea si no existe.
     * Usado por PaymentService al registrar el primer cobro.
     */
    public static function findOrCreateForCollector(User $collector, ?int $tenantId = null): static
    {
        return static::firstOrCreate(
            ['user_id' => $collector->id],
            ['tenant_id' => $tenantId ?? $collector->tenant_id, 'balance' => '0.00']
        );
    }

    // ─── Operaciones financieras ───────────────────────────────────────────────

    /**
     * Acredita un monto a la wallet dentro de una transacción con lock pesimista.
     *
     * NUNCA llamar a este método fuera de DB::transaction().
     * El lock previene el double-spending (R-1 del plan de riesgos).
     *
     * @throws \Throwable
     */
    public function credit(
        float   $amount,
        string  $description,
        ?int    $paymentId = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($amount, $description, $paymentId) {
            // SELECT FOR UPDATE — bloquea la fila hasta que el transaction termine
            $wallet = static::lockForUpdate()->findOrFail($this->id);

            // BCMath evita pérdida de precisión en aritmética de punto flotante
            $wallet->balance = bcadd((string) $wallet->balance, (string) $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'payment_id'   => $paymentId,
                'type'         => 'credit',
                'amount'       => $amount,
                'balance_after' => $wallet->balance,
                'description'  => $description,
            ]);
        });
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)
            ->orderByDesc('created_at');
    }
}
