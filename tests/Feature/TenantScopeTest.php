<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Family;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-007 — TenantScope: un usuario no puede ver datos de otro tenant.
 *
 * Mecanismo bajo prueba:
 *  - TenantScope filtra las queries Eloquent por `tenant_id` cuando
 *    `current_tenant` está enlazado en el container (lo hace SetTenantScope).
 *  - En tests, simulamos el middleware llamando a
 *    `app()->instance('current_tenant', $tenant)`.
 *  - super_admin (sin tenant) no tiene scope → ve todo.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Comunidad A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Comunidad B']);
    }

    protected function tearDown(): void
    {
        // Limpiar el binding entre tests para no contaminar otros
        app()->forgetInstance('current_tenant');
        parent::tearDown();
    }

    /** Helper: activa el TenantScope para un tenant concreto */
    private function bindTenant(Tenant $tenant): void
    {
        app()->instance('current_tenant', $tenant);
    }

    // ── Billing ───────────────────────────────────────────────────────────────

    public function test_billing_scope_hides_other_tenant_records(): void
    {
        $serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
        $serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
        $familyA  = Family::factory()->create(['tenant_id' => $this->tenantA->id]);
        $familyB  = Family::factory()->create(['tenant_id' => $this->tenantB->id]);

        $bA = Billing::factory()->create([
            'tenant_id'  => $this->tenantA->id,
            'family_id'  => $familyA->id,
        ]);
        BillingLine::create(['billing_id' => $bA->id, 'service_id' => $serviceA->id, 'amount' => 10]);

        $bB = Billing::factory()->create([
            'tenant_id'  => $this->tenantB->id,
            'family_id'  => $familyB->id,
        ]);
        BillingLine::create(['billing_id' => $bB->id, 'service_id' => $serviceB->id, 'amount' => 10]);

        $this->bindTenant($this->tenantA);

        $billings = Billing::all();

        $this->assertCount(1, $billings);
        $this->assertSame($this->tenantA->id, $billings->first()->tenant_id);
    }

    /** findOrFail() con un ID del tenant B lanza ModelNotFoundException cuando A está activo */
    public function test_billing_find_by_id_from_other_tenant_returns_null(): void
    {
        $serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
        $familyB  = Family::factory()->create(['tenant_id' => $this->tenantB->id]);
        $billingB = Billing::factory()->create([
            'tenant_id'  => $this->tenantB->id,
            'family_id'  => $familyB->id,
        ]);
        BillingLine::create(['billing_id' => $billingB->id, 'service_id' => $serviceB->id, 'amount' => 10]);

        $this->bindTenant($this->tenantA);

        // Intentar acceder al billing del tenant B via el ID conocido
        $found = Billing::find($billingB->id);

        $this->assertNull($found, 'El billing del tenant B no debe ser visible para el tenant A');
    }

    // ── Family ────────────────────────────────────────────────────────────────

    public function test_family_scope_isolates_by_tenant(): void
    {
        Family::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Family::factory()->count(5)->create(['tenant_id' => $this->tenantB->id]);

        $this->bindTenant($this->tenantA);

        $this->assertCount(3, Family::all());
    }

    // ── Service ───────────────────────────────────────────────────────────────

    public function test_service_scope_isolates_by_tenant(): void
    {
        Service::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Service::factory()->count(4)->create(['tenant_id' => $this->tenantB->id]);

        $this->bindTenant($this->tenantA);

        $this->assertCount(2, Service::all());
    }

    // ── Sector ────────────────────────────────────────────────────────────────

    public function test_sector_scope_isolates_by_tenant(): void
    {
        Sector::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Sector::factory()->count(3)->create(['tenant_id' => $this->tenantB->id]);

        $this->bindTenant($this->tenantA);

        $this->assertCount(2, Sector::all());
    }

    // ── Payment ───────────────────────────────────────────────────────────────

    public function test_payment_scope_isolates_by_tenant(): void
    {
        $serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
        $serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
        $familyA  = Family::factory()->create(['tenant_id' => $this->tenantA->id]);
        $familyB  = Family::factory()->create(['tenant_id' => $this->tenantB->id]);

        $billingA = Billing::factory()->create([
            'tenant_id'  => $this->tenantA->id,
            'family_id'  => $familyA->id,
        ]);
        BillingLine::create(['billing_id' => $billingA->id, 'service_id' => $serviceA->id, 'amount' => 10]);

        $billingB = Billing::factory()->create([
            'tenant_id'  => $this->tenantB->id,
            'family_id'  => $familyB->id,
        ]);
        BillingLine::create(['billing_id' => $billingB->id, 'service_id' => $serviceB->id, 'amount' => 10]);

        $collectorA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $collectorB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        Payment::factory()->create([
            'tenant_id'    => $this->tenantA->id,
            'billing_id'   => $billingA->id,
            'collector_id' => $collectorA->id,
        ]);
        Payment::factory()->create([
            'tenant_id'    => $this->tenantB->id,
            'billing_id'   => $billingB->id,
            'collector_id' => $collectorB->id,
        ]);

        $this->bindTenant($this->tenantA);

        $payments = Payment::all();
        $this->assertCount(1, $payments);
        $this->assertSame($this->tenantA->id, $payments->first()->tenant_id);
    }

    // ── Wallet ────────────────────────────────────────────────────────────────

    public function test_wallet_scope_isolates_by_tenant(): void
    {
        $collectorA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $collectorB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        Wallet::factory()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $collectorA->id]);
        Wallet::factory()->create(['tenant_id' => $this->tenantB->id, 'user_id' => $collectorB->id]);

        $this->bindTenant($this->tenantA);

        $wallets = Wallet::all();
        $this->assertCount(1, $wallets);
        $this->assertSame($this->tenantA->id, $wallets->first()->tenant_id);
    }

    // ── super_admin (sin scope) ───────────────────────────────────────────────

    public function test_super_admin_without_scope_sees_all_tenants(): void
    {
        Family::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Family::factory()->count(4)->create(['tenant_id' => $this->tenantB->id]);

        // Sin enlazar ningún tenant (super_admin no tiene tenant_id)
        $this->assertCount(7, Family::all());
    }

    /** withoutGlobalScopes() siempre retorna todos independientemente del scope activo */
    public function test_without_global_scopes_bypasses_tenant_filter(): void
    {
        Family::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Family::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        $this->bindTenant($this->tenantA);

        // Scope activo → solo 2
        $this->assertCount(2, Family::all());

        // Bypass explícito → los 4
        $this->assertCount(4, Family::withoutGlobalScopes()->get());
    }

    /** El scope se aplica también en relaciones Eloquent encadenadas */
    public function test_scope_applies_to_eager_loaded_relations(): void
    {
        $sectorA  = Sector::factory()->create(['tenant_id' => $this->tenantA->id]);
        $sectorB  = Sector::factory()->create(['tenant_id' => $this->tenantB->id]);
        $propA    = Property::factory()->create(['tenant_id' => $this->tenantA->id, 'sector_id' => $sectorA->id]);
        $propB    = Property::factory()->create(['tenant_id' => $this->tenantB->id, 'sector_id' => $sectorB->id]);

        Family::factory()->count(2)->create(['tenant_id' => $this->tenantA->id, 'property_id' => $propA->id]);
        Family::factory()->count(3)->create(['tenant_id' => $this->tenantB->id, 'property_id' => $propB->id]);

        $this->bindTenant($this->tenantA);

        // Families a través de la relación Sector → families()
        $families = $sectorA->families;
        $this->assertCount(2, $families);

        // El sector B no debe ser accesible
        $hiddenSector = Sector::find($sectorB->id);
        $this->assertNull($hiddenSector);
    }
}
