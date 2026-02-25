<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Family;
use App\Models\Jornada;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JornadaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $collector;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->collector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);
        $this->paymentService = app(PaymentService::class);
    }

    public function test_can_open_jornada(): void
    {
        $this->actingAs($this->collector);

        $response = $this->post(route('collector.jornadas.open'));
        $response->assertRedirect(route('collector.jornadas'));

        $this->assertDatabaseHas('jornadas', [
            'collector_id' => $this->collector->id,
            'status'       => 'open',
        ]);
    }

    public function test_cannot_open_second_jornada(): void
    {
        $this->actingAs($this->collector);

        Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $response = $this->post(route('collector.jornadas.open'));
        $response->assertSessionHasErrors('general');
    }

    public function test_can_close_jornada(): void
    {
        $this->actingAs($this->collector);

        $jornada = Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $response = $this->post(route('collector.jornadas.close', $jornada), [
            'notes' => 'Jornada completada',
        ]);

        $response->assertRedirect(route('collector.jornadas'));

        $jornada->refresh();
        $this->assertSame('closed', $jornada->status);
        $this->assertNotNull($jornada->closed_at);
        $this->assertSame('Jornada completada', $jornada->notes);
    }

    public function test_payment_linked_to_active_jornada(): void
    {
        $jornada = Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $family  = Family::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'default_price' => '50.00']);
        $billing = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $family->id,
            'amount'     => '50.00',
            'status'     => 'pending',
        ]);
        BillingLine::create([
            'billing_id' => $billing->id,
            'service_id' => $service->id,
            'amount'     => '50.00',
        ]);

        $payment = $this->paymentService->register(
            billing:       $billing,
            collector:     $this->collector,
            amount:        50.00,
            paymentMethod: 'cash',
            jornada:       $jornada,
        );

        $this->assertSame($jornada->id, $payment->jornada_id);

        $jornada->refresh();
        $this->assertEquals('50.00', $jornada->total_collected);
    }

    public function test_multiple_jornadas_per_day(): void
    {
        // Primera jornada
        $j1 = Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);
        $j1->close();

        // Segunda jornada el mismo día
        $j2 = Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $this->collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $this->assertTrue($j1->isClosed());
        $this->assertTrue($j2->isOpen());

        $todayCount = Jornada::withoutGlobalScopes()
            ->where('collector_id', $this->collector->id)
            ->whereDate('opened_at', today())
            ->count();

        $this->assertSame(2, $todayCount);
    }

    public function test_cannot_close_other_collectors_jornada(): void
    {
        $otherCollector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);

        $jornada = Jornada::create([
            'tenant_id'    => $this->tenant->id,
            'collector_id' => $otherCollector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $this->actingAs($this->collector);

        $response = $this->post(route('collector.jornadas.close', $jornada));
        $response->assertForbidden();
    }
}
