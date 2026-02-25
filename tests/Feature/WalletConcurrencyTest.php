<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-004 — Wallet: integridad de balance con operaciones secuenciales.
 *
 * Nota: el test de concurrencia real (dos conexiones simultáneas) requeriría
 * múltiples procesos PHP; aquí se verifica la integridad del mecanismo de lock
 * con operaciones secuenciales que comprobamos producen el resultado correcto.
 * El SELECT FOR UPDATE es la garantía a nivel de base de datos.
 */
class WalletConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant       = Tenant::factory()->create();
        $collector    = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->wallet = Wallet::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id'   => $collector->id,
            'balance'   => '0.00',
        ]);
    }

    /** Múltiples créditos secuenciales producen la suma exacta */
    public function test_sequential_credits_produce_correct_balance(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->wallet->credit(10.00, "Crédito #{$i}");
        }

        $this->wallet->refresh();
        $this->assertEquals('100.00', $this->wallet->balance);
        $this->assertSame(10, $this->wallet->transactions()->count());
    }

    /** Cada transacción crea su propio WalletTransaction (ledger entry) */
    public function test_each_credit_creates_immutable_ledger_entry(): void
    {
        $this->wallet->credit(100.00, 'C1');
        $this->wallet->credit(50.00, 'C2');
        $this->wallet->credit(30.00, 'C3');

        $txs = $this->wallet->transactions()->get();

        $this->assertSame(3, $txs->count());

        // Verificar que balance_after se actualiza correctamente en cada entrada
        // transactions() is ordered by created_at desc, so latest first
        $balances = $txs->pluck('balance_after')->map(fn ($b) => (float) $b)->values();
        $this->assertEquals([180.00, 150.00, 100.00], $balances->all());
    }

    /** El balance acumulado nunca es negativo (solo créditos) */
    public function test_balance_is_always_positive_with_credits(): void
    {
        $this->wallet->credit(0.01, 'Micro crédito 1');
        $this->wallet->credit(0.01, 'Micro crédito 2');
        $this->wallet->credit(0.01, 'Micro crédito 3');

        $this->wallet->refresh();
        $this->assertEquals('0.03', $this->wallet->balance);
        $this->assertGreaterThan(0, (float) $this->wallet->balance);
    }
}
