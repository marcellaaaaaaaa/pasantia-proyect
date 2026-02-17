<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-005 — Wallet::credit() y Wallet::debit()
 */
class WalletTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant    = Tenant::factory()->create();
        $collector = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->wallet = Wallet::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id'   => $collector->id,
            'balance'   => '0.00',
        ]);
    }

    /** credit() incrementa el saldo con precisión decimal */
    public function test_credit_increments_balance(): void
    {
        $this->wallet->credit(75.50, 'Cobro de prueba');

        $this->wallet->refresh();
        $this->assertEquals('75.50', $this->wallet->balance);
    }

    /** credit() retorna un WalletTransaction con type=credit */
    public function test_credit_returns_wallet_transaction(): void
    {
        $tx = $this->wallet->credit(50.00, 'Descripción de prueba', paymentId: null);

        $this->assertInstanceOf(WalletTransaction::class, $tx);
        $this->assertSame('credit', $tx->type);
        $this->assertEquals('50.00', $tx->amount);
        $this->assertEquals('50.00', $tx->balance_after);
        $this->assertSame($this->wallet->id, $tx->wallet_id);
    }

    /** credit() vincula el payment_id cuando se proporciona */
    public function test_credit_stores_payment_id(): void
    {
        // Crear un Payment real para no violar la FK wallet_transactions.payment_id
        $payment = \App\Models\Payment::factory()->create([
            'tenant_id'    => $this->wallet->tenant_id,
            'collector_id' => $this->wallet->user_id,
        ]);

        $tx = $this->wallet->credit(25.00, 'Pago vinculado', paymentId: $payment->id);

        $this->assertSame($payment->id, $tx->payment_id);
    }

    /** debit() reduce el saldo correctamente */
    public function test_debit_decrements_balance(): void
    {
        $this->wallet->credit(200.00, 'Fondos');
        $this->wallet->debit(80.00, 'Liquidación');

        $this->wallet->refresh();
        $this->assertEquals('120.00', $this->wallet->balance);
    }

    /** debit() lanza InsufficientBalanceException si saldo < amount (R-3) */
    public function test_debit_throws_insufficient_balance_exception(): void
    {
        $this->wallet->credit(50.00, 'Fondos');

        try {
            $this->wallet->debit(100.00, 'Sobregiro');
            $this->fail('Se esperaba InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertEquals(100.00, $e->requested);
            $this->assertEquals(50.00, $e->available);
        }
    }

    /** debit() lanza excepción incluso si la diferencia es un centavo */
    public function test_debit_throws_on_one_cent_overdraft(): void
    {
        $this->wallet->credit(10.00, 'Fondos');

        $this->expectException(InsufficientBalanceException::class);

        $this->wallet->debit(10.01, 'Un centavo de más');
    }

    /** Saldo exactamente igual al débito es permitido */
    public function test_debit_exact_balance_is_allowed(): void
    {
        $this->wallet->credit(100.00, 'Fondos');
        $this->wallet->debit(100.00, 'Débito total');

        $this->wallet->refresh();
        $this->assertEquals('0.00', $this->wallet->balance);
    }

    /** BCMath mantiene precisión con montos con muchos decimales */
    public function test_bcmath_precision_with_fractional_amounts(): void
    {
        // 0.1 + 0.2 con float nativo = 0.30000000000000004
        $this->wallet->credit(0.10, 'Décima');
        $this->wallet->credit(0.20, 'Quinta');

        $this->wallet->refresh();
        $this->assertEquals('0.30', $this->wallet->balance);
    }

    /** El ledger es inmutable: no se puede modificar una WalletTransaction */
    public function test_wallet_transaction_is_immutable(): void
    {
        $tx = $this->wallet->credit(10.00, 'Original');

        $this->expectException(\LogicException::class);

        $tx->description = 'Modificado';
        $tx->save();
    }

    /** El ledger es inmutable: no se puede eliminar una WalletTransaction */
    public function test_wallet_transaction_cannot_be_deleted(): void
    {
        $tx = $this->wallet->credit(10.00, 'A eliminar');

        $this->expectException(\LogicException::class);

        $tx->delete();
    }
}
