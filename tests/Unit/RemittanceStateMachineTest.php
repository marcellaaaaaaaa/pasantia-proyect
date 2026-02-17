<?php

namespace Tests\Unit;

use App\Models\Remittance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * TST-006 — Remittance state machine: transiciones válidas e inválidas
 */
class RemittanceStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => User::ROLE_ADMIN,
        ]);

        $this->collector = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => User::ROLE_COLLECTOR,
        ]);
    }

    /** Helper: crea remesa con el estado dado */
    private function makeRemittance(string $status, float $declared = 100.00): Remittance
    {
        return Remittance::create([
            'tenant_id'       => $this->admin->tenant_id,
            'collector_id'    => $this->collector->id,
            'amount_declared' => (string) $declared,
            'status'          => $status,
        ]);
    }

    // ── Helpers de estado ──────────────────────────────────────────────────────

    public function test_is_draft_returns_true_for_draft(): void
    {
        $this->assertTrue($this->makeRemittance('draft')->isDraft());
        $this->assertFalse($this->makeRemittance('submitted')->isDraft());
    }

    public function test_is_submitted_returns_true_for_submitted(): void
    {
        $this->assertTrue($this->makeRemittance('submitted')->isSubmitted());
        $this->assertFalse($this->makeRemittance('draft')->isSubmitted());
    }

    public function test_is_approved_returns_true_for_approved(): void
    {
        $this->assertTrue($this->makeRemittance('approved')->isApproved());
        $this->assertFalse($this->makeRemittance('rejected')->isApproved());
    }

    public function test_is_rejected_returns_true_for_rejected(): void
    {
        $this->assertTrue($this->makeRemittance('rejected')->isRejected());
        $this->assertFalse($this->makeRemittance('approved')->isRejected());
    }

    // ── submit() ───────────────────────────────────────────────────────────────

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $remittance = $this->makeRemittance('draft');
        $remittance->submit('Notas del cobrador');

        $this->assertSame('submitted', $remittance->fresh()->status);
        $this->assertNotNull($remittance->fresh()->submitted_at);
        $this->assertSame('Notas del cobrador', $remittance->fresh()->collector_notes);
    }

    public function test_submit_throws_if_not_draft(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('submitted')->submit();
    }

    public function test_submit_throws_from_approved(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('approved')->submit();
    }

    public function test_submit_throws_from_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('rejected')->submit();
    }

    // ── markApproved() ─────────────────────────────────────────────────────────

    public function test_mark_approved_transitions_submitted_to_approved(): void
    {
        $remittance = $this->makeRemittance('submitted');
        $remittance->markApproved($this->admin, 95.00, 'Verificado correctamente');

        $fresh = $remittance->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertEquals('95.00', $fresh->amount_confirmed);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertSame('Verificado correctamente', $fresh->admin_notes);
    }

    public function test_mark_approved_throws_if_not_submitted(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('draft')->markApproved($this->admin, 100.00);
    }

    public function test_mark_approved_throws_if_already_approved(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('approved')->markApproved($this->admin, 100.00);
    }

    public function test_mark_approved_throws_if_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('rejected')->markApproved($this->admin, 100.00);
    }

    // ── markRejected() ─────────────────────────────────────────────────────────

    public function test_mark_rejected_transitions_submitted_to_rejected(): void
    {
        $remittance = $this->makeRemittance('submitted');
        $remittance->markRejected($this->admin, 'Montos incorrectos');

        $fresh = $remittance->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertSame('Montos incorrectos', $fresh->admin_notes);
    }

    public function test_mark_rejected_throws_if_not_submitted(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('draft')->markRejected($this->admin, 'Motivo');
    }

    public function test_mark_rejected_throws_if_already_approved(): void
    {
        $this->expectException(LogicException::class);
        $this->makeRemittance('approved')->markRejected($this->admin, 'Motivo');
    }

    /** Estado diagram completo: draft → submitted → approved */
    public function test_full_happy_path_draft_to_approved(): void
    {
        $remittance = $this->makeRemittance('draft');

        $remittance->submit();
        $this->assertSame('submitted', $remittance->fresh()->status);

        $remittance->fresh()->markApproved($this->admin, 100.00);
        $this->assertSame('approved', $remittance->fresh()->status);
    }

    /** Estado diagram alternativo: draft → submitted → rejected */
    public function test_full_rejection_path_draft_to_rejected(): void
    {
        $remittance = $this->makeRemittance('draft');

        $remittance->submit();
        $remittance->fresh()->markRejected($this->admin, 'Error en montos');

        $this->assertSame('rejected', $remittance->fresh()->status);
    }
}
