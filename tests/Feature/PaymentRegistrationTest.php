<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Family;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * TST-002 — PaymentService::register()
 */
class PaymentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private Tenant $tenant;
    private User $collector;
    private Billing $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentService::class);

        $this->tenant = Tenant::factory()->create();

        $this->collector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);

        $family  = Family::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'default_price' => '100.00',
        ]);

        $this->billing = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $family->id,
            'amount'     => '100.00',
            'status'     => 'pending',
        ]);

        BillingLine::create([
            'billing_id' => $this->billing->id,
            'service_id' => $service->id,
            'amount'     => '100.00',
        ]);
    }

    /** Pago completo acredita la wallet y marca el billing como paid */
    public function test_registers_full_payment_and_credits_wallet(): void
    {
        $payment = $this->service->register(
            billing:       $this->billing,
            collector:     $this->collector,
            amount:        100.00,
            paymentMethod: 'cash',
        );

        $this->assertDatabaseHas('payments', [
            'id'             => $payment->id,
            'billing_id'     => $this->billing->id,
            'collector_id'   => $this->collector->id,
            'amount'         => '100.00',
            'status'         => 'paid',
            'payment_method' => 'cash',
        ]);

        // Wallet creada y acreditada
        $wallet = Wallet::withoutGlobalScopes()
            ->where('user_id', $this->collector->id)
            ->first();

        $this->assertNotNull($wallet);
        $this->assertEquals('100.00', $wallet->balance);

        // Billing debe quedar en paid (via PaymentObserver)
        $this->assertSame('paid', $this->billing->fresh()->status);
    }

    /** Pago parcial crea wallet entry y deja billing en partial */
    public function test_registers_partial_payment_and_sets_billing_to_partial(): void
    {
        $this->service->register(
            billing:       $this->billing,
            collector:     $this->collector,
            amount:        60.00,
            paymentMethod: 'bank_transfer',
            reference:     'REF-001',
        );

        $this->assertSame('partial', $this->billing->fresh()->status);

        $wallet = Wallet::withoutGlobalScopes()
            ->where('user_id', $this->collector->id)
            ->first();

        $this->assertEquals('60.00', $wallet->balance);
    }

    /** Pago que completa un billing parcial lo lleva a paid */
    public function test_second_payment_completes_billing(): void
    {
        $this->service->register($this->billing, $this->collector, 40.00, 'cash');
        $this->service->register($this->billing->fresh(), $this->collector, 60.00, 'cash');

        $this->assertSame('paid', $this->billing->fresh()->status);

        $wallet = Wallet::withoutGlobalScopes()
            ->where('user_id', $this->collector->id)
            ->first();

        $this->assertEquals('100.00', $wallet->balance);
        $this->assertSame(2, $wallet->transactions()->count());
    }

    /** No se puede pagar un billing ya pagado */
    public function test_rejects_payment_on_paid_billing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $paid = Billing::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount'    => '50.00',
            'status'    => 'paid',
        ]);

        $this->service->register($paid, $this->collector, 50.00, 'cash');
    }

    /** Monto superior al pendiente es rechazado */
    public function test_rejects_amount_exceeding_pending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->register(
            billing:       $this->billing,
            collector:     $this->collector,
            amount:        999.00,  // mayor que 100.00
            paymentMethod: 'cash',
        );
    }

    /** Monto cero o negativo es rechazado */
    public function test_rejects_zero_or_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->register($this->billing, $this->collector, 0.00, 'cash');
    }
}
