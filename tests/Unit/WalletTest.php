<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-005 — Wallet::credit()
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
