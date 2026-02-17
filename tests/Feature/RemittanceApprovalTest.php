<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\CommunityVault;
use App\Models\Family;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentService;
use App\Services\RemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * TST-003 — RemittanceService: create(), submit(), approve(), reject()
 */
class RemittanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private RemittanceService $service;
    private PaymentService $paymentService;
    private Tenant $tenant;
    private User $collector;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service        = app(RemittanceService::class);
        $this->paymentService = app(PaymentService::class);

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_ADMIN,
        ]);

        $this->collector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);
    }

    /** Helper: registra N pagos y retorna los IDs */
    private function registerPayments(int $count = 2, float $amount = 50.00): void
    {
        $service = Service::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'default_price' => (string) $amount,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $family  = Family::factory()->create(['tenant_id' => $this->tenant->id]);
            $billing = Billing::factory()->create([
                'tenant_id'  => $this->tenant->id,
                'family_id'  => $family->id,
                'service_id' => $service->id,
                'amount'     => (string) $amount,
                'status'     => 'pending',
            ]);

            $this->paymentService->register($billing, $this->collector, $amount, 'cash');
        }
    }

    /** create() agrupa todos los pending_remittance del cobrador en draft */
    public function test_creates_remittance_draft_from_pending_payments(): void
    {
        $this->registerPayments(count: 3, amount: 50.00);

        $remittance = $this->service->create($this->collector);

        $this->assertSame('draft', $remittance->status);
        $this->assertEquals('150.00', $remittance->amount_declared);
        $this->assertSame(3, $remittance->payments()->count());
        $this->assertDatabaseHas('remittances', ['id' => $remittance->id, 'status' => 'draft']);
    }

    /** create() lanza excepción si no hay pagos pendientes */
    public function test_create_throws_when_no_pending_payments(): void
    {
        $this->expectException(LogicException::class);
        $this->service->create($this->collector);
    }

    /** submit() transiciona draft → submitted */
    public function test_submit_transitions_draft_to_submitted(): void
    {
        $this->registerPayments();
        $remittance = $this->service->create($this->collector);

        $this->service->submit($remittance);

        $this->assertSame('submitted', $remittance->fresh()->status);
        $this->assertNotNull($remittance->fresh()->submitted_at);
    }

    /** approve() ejecuta el flujo financiero completo con balances correctos */
    public function test_approve_transfers_funds_correctly(): void
    {
        $this->registerPayments(count: 2, amount: 50.00); // total $100

        $remittance = $this->service->create($this->collector);
        $this->service->submit($remittance);

        $walletBefore = (float) Wallet::withoutGlobalScopes()
            ->where('user_id', $this->collector->id)
            ->value('balance');

        $this->service->approve($remittance->fresh(), $this->admin, 100.00);

        // 1. Remesa aprobada
        $fresh = $remittance->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertEquals('100.00', $fresh->amount_confirmed);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);

        // 2. Pagos conciliados
        foreach ($remittance->payments as $payment) {
            $this->assertSame('conciliated', $payment->fresh()->status);
        }

        // 3. Wallet debitada
        $walletAfter = (float) Wallet::withoutGlobalScopes()
            ->where('user_id', $this->collector->id)
            ->value('balance');

        $this->assertEquals(
            bcadd((string) ($walletBefore - 100.00), '0', 2),
            number_format($walletAfter, 2, '.', ''),
        );

        // 4. Vault acreditado
        $vault = CommunityVault::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($vault);
        $this->assertEquals('100.00', $vault->balance);

        // 5. Ledger entries creados
        $this->assertSame(1, $vault->transactions()->count());
    }

    /** reject() libera los pagos del pivot y los mantiene pending_remittance */
    public function test_reject_frees_payments_for_future_remittance(): void
    {
        $this->registerPayments(count: 2, amount: 30.00);

        $remittance = $this->service->create($this->collector);
        $this->service->submit($remittance);

        $this->service->reject($remittance->fresh(), $this->admin, 'Los montos no coinciden con el recibo físico.');

        $fresh = $remittance->fresh();
        $this->assertSame('rejected', $fresh->status);

        // Pivot vaciado — los pagos están libres para otra remesa
        $this->assertSame(0, $fresh->payments()->count());
        $this->assertDatabaseCount('remittance_payments', 0);

        // Los pagos conservan su status (pueden volver a liquidarse)
        foreach ($remittance->payments as $payment) {
            $this->assertSame('pending_remittance', $payment->fresh()->status);
        }
    }

    /** approve() con monto mayor que declarado + tolerancia lanza excepción */
    public function test_approve_rejects_amount_exceeding_tolerance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registerPayments(count: 1, amount: 100.00);

        $remittance = $this->service->create($this->collector);
        $this->service->submit($remittance);

        // 5% de tolerancia sobre $100 = máximo $105; aquí enviamos $110
        $this->service->approve($remittance->fresh(), $this->admin, 110.00);
    }

    /** R-2: un pago solo puede estar en UNA remesa activa */
    public function test_payment_cannot_belong_to_two_remittances(): void
    {
        $this->registerPayments(count: 1, amount: 50.00);

        $remittance1 = $this->service->create($this->collector);

        // Intentar crear otra remesa cuando todos los pagos ya están en remittance1
        $this->expectException(LogicException::class);
        $this->service->create($this->collector); // no hay pagos pending_remittance
    }
}
