<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CommunityVault extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    protected $fillable = [
        'tenant_id',
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
     * Obtiene el vault del tenant o lo crea si no existe.
     * UNIQUE(tenant_id) garantiza que solo existe uno por tenant.
     */
    public static function findOrCreateForTenant(Tenant $tenant): static
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['balance'   => '0.00'],
        );
    }

    // ─── Operaciones financieras ───────────────────────────────────────────────

    /**
     * Acredita el vault central con lock pesimista.
     *
     * Debe llamarse desde dentro de DB::transaction() (RemittanceService::approve).
     * El lock (SELECT FOR UPDATE) previene race conditions (R-1).
     *
     * @throws \Throwable
     */
    public function credit(
        float   $amount,
        string  $description,
        int     $remittanceId,
    ): VaultTransaction {
        return DB::transaction(function () use ($amount, $description, $remittanceId) {
            // SELECT FOR UPDATE — bloquea el vault hasta que la transaction termine
            $vault = static::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($this->id);

            $vault->balance = bcadd((string) $vault->balance, (string) $amount, 2);
            $vault->save();

            return VaultTransaction::create([
                'vault_id'      => $vault->id,
                'remittance_id' => $remittanceId,
                'type'          => 'credit',
                'amount'        => $amount,
                'balance_after' => $vault->balance,
                'description'   => $description,
            ]);
        });
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VaultTransaction::class, 'vault_id')
                    ->orderByDesc('created_at');
    }
}
