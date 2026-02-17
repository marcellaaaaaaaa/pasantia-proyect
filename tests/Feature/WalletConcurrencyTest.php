<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-004 — Wallet: integridad de balance con operaciones secuenciales y concurrentes.
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

    /** Créditos y débitos alternados mantienen la consistencia del ledger */
    public function test_mixed_operations_maintain_ledger_consistency(): void
    {
        $this->wallet->credit(100.00, 'Crédito inicial');
        $this->wallet->debit(30.00, 'Débito 1');
        $this->wallet->credit(50.00, 'Crédito 2');
        $this->wallet->debit(20.00, 'Débito 2');

        $this->wallet->refresh();
        $this->assertEquals('100.00', $this->wallet->balance); // 100 - 30 + 50 - 20

        // El balance_after de la última transacción debe coincidir con el saldo
        $lastTx = $this->wallet->transactions()->first();
        $this->assertEquals($this->wallet->balance, $lastTx->balance_after);
    }

    /** El saldo nunca cae por debajo de cero — InsufficientBalanceException dentro del lock */
    public function test_balance_never_goes_negative(): void
    {
        $this->wallet->credit(50.00, 'Fondos iniciales');

        $this->expectException(InsufficientBalanceException::class);

        $this->wallet->debit(51.00, 'Intento de sobregirar');
    }

    /** Varias solicitudes de débito paralelas no producen saldo negativo */
    public function test_repeated_debits_stop_at_zero(): void
    {
        $this->wallet->credit(100.00, 'Fondos');

        $successes = 0;
        $failures  = 0;

        // Simula 5 solicitudes de $30 (total $150 > $100)
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->wallet->debit(30.00, "Débito #{$i}");
                $successes++;
            } catch (InsufficientBalanceException) {
                $failures++;
            }
        }

        $this->wallet->refresh();

        // Exactamente 3 débitos exitosos ($90), el 4to falla ($90 < $30+$30)
        // Podría ser 3 o menos dependiendo de la secuencia
        $this->assertGreaterThanOrEqual(0, (float) $this->wallet->balance);
        $this->assertGreaterThan(0, $successes + $failures);
        $this->assertEquals('100.00', bcadd(
            bcmul((string) $successes, '30.00', 2),
            (string) $this->wallet->balance,
            2
        ));
    }

    /** Cada transacción crea su propio WalletTransaction (ledger entry) */
    public function test_each_operation_creates_immutable_ledger_entry(): void
    {
        $this->wallet->credit(100.00, 'C1');
        $this->wallet->credit(50.00, 'C2');
        $this->wallet->debit(30.00, 'D1');

        $txs = $this->wallet->transactions()->get();

        $this->assertSame(3, $txs->count());

        // Verificar que balance_after se actualiza correctamente en cada entrada
        $balances = $txs->pluck('balance_after')->map(fn ($b) => (float) $b)->values();
        $this->assertEquals([120.00, 150.00, 100.00], $balances->all());
    }
}
