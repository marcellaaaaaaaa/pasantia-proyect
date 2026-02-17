<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Family;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\BillingGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-001 — BillingGenerationService::generateForTenant()
 */
class BillingGenerationTest extends TestCase
{
    use RefreshDatabase;

    private BillingGenerationService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BillingGenerationService::class);
        $this->tenant  = Tenant::factory()->create();
    }

    /** Crea un billing por cada combinación familia × servicio */
    public function test_generates_billings_for_all_active_families_and_services(): void
    {
        $families = Family::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $services = Service::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->generateForTenant($this->tenant, '2026-01');

        $this->assertSame(6, $result['created']);  // 3 families × 2 services
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('billings', 6);
    }

    /** Idempotencia: llamar dos veces no duplica los billings (R-4) */
    public function test_is_idempotent_on_second_run(): void
    {
        Family::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->service->generateForTenant($this->tenant, '2026-02');
        $result = $this->service->generateForTenant($this->tenant, '2026-02');

        $this->assertSame(0, $result['created']);
        $this->assertSame(4, $result['skipped']);
        $this->assertDatabaseCount('billings', 4); // no duplicados
    }

    /** Periodos distintos generan billings independientes */
    public function test_different_periods_generate_independent_billings(): void
    {
        Family::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->generateForTenant($this->tenant, '2026-01');
        $this->service->generateForTenant($this->tenant, '2026-02');

        $this->assertDatabaseCount('billings', 2);
    }

    /** Familias inactivas no generan billings */
    public function test_skips_inactive_families(): void
    {
        Family::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Family::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);
        Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->generateForTenant($this->tenant, '2026-03');

        $this->assertSame(1, $result['created']); // solo la familia activa
    }

    /** Servicios inactivos no generan billings */
    public function test_skips_inactive_services(): void
    {
        Family::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $result = $this->service->generateForTenant($this->tenant, '2026-03');

        $this->assertSame(1, $result['created']); // solo el servicio activo
    }

    /** El billing creado usa el precio del servicio y due_date = fin de mes */
    public function test_billing_uses_service_price_and_end_of_month_due_date(): void
    {
        $family  = Family::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'default_price' => '75.50',
        ]);

        $this->service->generateForTenant($this->tenant, '2026-06');

        $billing = Billing::withoutGlobalScopes()
            ->where('family_id', $family->id)
            ->where('service_id', $service->id)
            ->first();

        $this->assertNotNull($billing);
        $this->assertEquals('75.50', $billing->amount);
        $this->assertSame('2026-06-30', $billing->due_date->toDateString());
        $this->assertSame('pending', $billing->status);
    }

    /** El comando Artisan billing:generate se registra y funciona */
    public function test_artisan_command_runs_successfully(): void
    {
        Family::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->artisan('billing:generate', ['--period' => '2026-05'])
             ->assertSuccessful();

        $this->assertDatabaseCount('billings', 1);
    }

    /** El comando rechaza períodos con formato inválido */
    public function test_artisan_command_fails_with_invalid_period(): void
    {
        $this->artisan('billing:generate', ['--period' => 'invalid'])
             ->assertFailed();
    }
}
