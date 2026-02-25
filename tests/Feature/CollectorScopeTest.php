<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingLine;
use App\Models\Family;
use App\Models\Property;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TST-008 — CollectorScope: el cobrador solo puede ver familias y
 * cobros de los sectores que le fueron asignados.
 *
 * No existe un Eloquent scope automático para esto; se verifica el patrón
 * de consulta que usarán los endpoints de la PWA:
 *
 *   Family::whereHas('property.sector.collectors',
 *       fn ($q) => $q->where('users.id', $collector->id)
 *   )
 *
 * TST-008 garantiza que el modelo de datos y las relaciones soportan
 * correctamente este aislamiento.
 */
class CollectorScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    /** Sectores */
    private Sector $sectorA;
    private Sector $sectorB;

    /** Propiedades por sector */
    private Property $propA;
    private Property $propB;

    /** Families por sector */
    private Family $familyA1;
    private Family $familyA2;
    private Family $familyB1;

    /** Cobrador asignado solo al sector A */
    private User $collectorA;
    /** Cobrador asignado solo al sector B */
    private User $collectorB;
    /** Cobrador sin sector asignado */
    private User $collectorUnassigned;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Sectores
        $this->sectorA = Sector::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sector A']);
        $this->sectorB = Sector::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sector B']);

        // Propiedades
        $this->propA = Property::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sector_id' => $this->sectorA->id,
        ]);
        $this->propB = Property::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sector_id' => $this->sectorB->id,
        ]);

        // Familias: 2 en sector A, 1 en sector B
        $this->familyA1 = Family::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'property_id' => $this->propA->id,
        ]);
        $this->familyA2 = Family::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'property_id' => $this->propA->id,
        ]);
        $this->familyB1 = Family::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'property_id' => $this->propB->id,
        ]);

        // Cobradores
        $this->collectorA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);
        $this->collectorB = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);
        $this->collectorUnassigned = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);

        // Asignaciones de sectores
        $this->sectorA->collectors()->attach($this->collectorA->id, ['assigned_at' => now()]);
        $this->sectorB->collectors()->attach($this->collectorB->id, ['assigned_at' => now()]);
    }

    /** Helper: query de familias filtradas por sectores asignados al cobrador */
    private function familiesForCollector(User $collector)
    {
        return Family::withoutGlobalScopes()
            ->where('tenant_id', $collector->tenant_id)
            ->whereHas('property.sector.collectors', function ($q) use ($collector) {
                $q->where('users.id', $collector->id);
            });
    }

    /** Helper: query de billings filtrados por sectores del cobrador */
    private function billingsForCollector(User $collector)
    {
        return Billing::withoutGlobalScopes()
            ->where('tenant_id', $collector->tenant_id)
            ->whereHas('family.property.sector.collectors', function ($q) use ($collector) {
                $q->where('users.id', $collector->id);
            });
    }

    // ── Relaciones del modelo de datos ────────────────────────────────────────

    /** El pivot sector_user registra la asignación correctamente */
    public function test_sector_user_pivot_records_assignment(): void
    {
        $this->assertTrue(
            $this->sectorA->collectors->contains($this->collectorA)
        );
        $this->assertFalse(
            $this->sectorA->collectors->contains($this->collectorB)
        );
    }

    /** User::sectors() retorna los sectores asignados al cobrador */
    public function test_collector_sectors_relation_returns_assigned_sectors(): void
    {
        $sectors = $this->collectorA->sectors;

        $this->assertCount(1, $sectors);
        $this->assertSame($this->sectorA->id, $sectors->first()->id);
    }

    /** Un cobrador sin asignaciones tiene sectores vacíos */
    public function test_unassigned_collector_has_no_sectors(): void
    {
        $this->assertCount(0, $this->collectorUnassigned->sectors);
    }

    /** Sector::families() retorna las familias del sector via Property */
    public function test_sector_families_through_properties(): void
    {
        $families = $this->sectorA->families;

        $this->assertCount(2, $families);
        $this->assertTrue($families->contains($this->familyA1));
        $this->assertTrue($families->contains($this->familyA2));
        $this->assertFalse($families->contains($this->familyB1));
    }

    // ── Aislamiento por sector asignado ───────────────────────────────────────

    /** El cobrador A solo ve familias del sector A */
    public function test_collector_a_only_sees_sector_a_families(): void
    {
        $families = $this->familiesForCollector($this->collectorA)->get();

        $this->assertCount(2, $families);
        $this->assertTrue($families->contains($this->familyA1));
        $this->assertTrue($families->contains($this->familyA2));
        $this->assertFalse($families->contains($this->familyB1));
    }

    /** El cobrador B solo ve familias del sector B */
    public function test_collector_b_only_sees_sector_b_families(): void
    {
        $families = $this->familiesForCollector($this->collectorB)->get();

        $this->assertCount(1, $families);
        $this->assertTrue($families->contains($this->familyB1));
        $this->assertFalse($families->contains($this->familyA1));
    }

    /** Un cobrador sin sector asignado no ve ninguna familia */
    public function test_unassigned_collector_sees_no_families(): void
    {
        $families = $this->familiesForCollector($this->collectorUnassigned)->get();

        $this->assertCount(0, $families);
    }

    /** El cobrador A no puede acceder a una familia del sector B por ID directo */
    public function test_collector_a_cannot_access_sector_b_family_by_id(): void
    {
        $found = $this->familiesForCollector($this->collectorA)
            ->where('id', $this->familyB1->id)
            ->first();

        $this->assertNull($found, 'El cobrador A no debe ver la familia del sector B');
    }

    // ── Billings filtrados por sector ────────────────────────────────────────

    /** El cobrador A solo ve los cobros de sus familias (sector A) */
    public function test_collector_sees_only_billings_from_assigned_sectors(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        // Billing para sector A y para sector B
        $billingA = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $this->familyA1->id,
        ]);
        BillingLine::create(['billing_id' => $billingA->id, 'service_id' => $service->id, 'amount' => 10]);

        $billingB = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $this->familyB1->id,
        ]);
        BillingLine::create(['billing_id' => $billingB->id, 'service_id' => $service->id, 'amount' => 10]);

        $billingsA = $this->billingsForCollector($this->collectorA)->get();

        $this->assertCount(1, $billingsA);
        $this->assertSame($billingA->id, $billingsA->first()->id);
        $this->assertFalse($billingsA->contains($billingB));
    }

    /** Un cobrador asignado a múltiples sectores ve todos sus cobros */
    public function test_collector_assigned_to_multiple_sectors_sees_all_their_billings(): void
    {
        // Asignar el cobrador A también al sector B
        $this->sectorB->collectors()->attach($this->collectorA->id, ['assigned_at' => now()]);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $bA = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $this->familyA1->id,
        ]);
        BillingLine::create(['billing_id' => $bA->id, 'service_id' => $service->id, 'amount' => 10]);

        $bB = Billing::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'family_id'  => $this->familyB1->id,
        ]);
        BillingLine::create(['billing_id' => $bB->id, 'service_id' => $service->id, 'amount' => 10]);

        $billings = $this->billingsForCollector($this->collectorA)->get();

        $this->assertCount(2, $billings);
    }

    // ── Integridad del TenantScope combinado ──────────────────────────────────

    /** Un cobrador del tenant A no puede ver familias del tenant B aunque use el mismo patrón de query */
    public function test_sector_query_respects_tenant_boundary(): void
    {
        $tenantB = Tenant::factory()->create();

        $sectorB2  = Sector::factory()->create(['tenant_id' => $tenantB->id]);
        $propB2    = Property::factory()->create(['tenant_id' => $tenantB->id, 'sector_id' => $sectorB2->id]);
        $familyB2  = Family::factory()->create(['tenant_id' => $tenantB->id, 'property_id' => $propB2->id]);

        // Asignar collectorA (tenant A) al sector del tenant B — situación imposible
        // en la app real, pero la query no debe cruzar el filtro de tenant_id
        $sectorB2->collectors()->attach($this->collectorA->id, ['assigned_at' => now()]);

        $families = $this->familiesForCollector($this->collectorA)->get();

        // El filtro `tenant_id = collectorA->tenant_id` impide ver familyB2
        $this->assertFalse($families->contains($familyB2));
    }
}
