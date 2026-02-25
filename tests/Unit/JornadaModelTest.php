<?php

namespace Tests\Unit;

use App\Models\Jornada;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class JornadaModelTest extends TestCase
{
    use RefreshDatabase;

    private Jornada $jornada;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant    = Tenant::factory()->create();
        $collector = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->jornada = Jornada::create([
            'tenant_id'    => $tenant->id,
            'collector_id' => $collector->id,
            'status'       => 'open',
            'opened_at'    => now(),
        ]);
    }

    public function test_is_open(): void
    {
        $this->assertTrue($this->jornada->isOpen());
        $this->assertFalse($this->jornada->isClosed());
    }

    public function test_is_closed_after_close(): void
    {
        $this->jornada->close();

        $this->assertTrue($this->jornada->isClosed());
        $this->assertFalse($this->jornada->isOpen());
        $this->assertNotNull($this->jornada->closed_at);
    }

    public function test_close_throws_if_already_closed(): void
    {
        $this->jornada->close();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('La jornada ya está cerrada.');

        $this->jornada->close();
    }

    public function test_close_saves_notes(): void
    {
        $this->jornada->close('Notas de cierre');

        $this->assertSame('Notas de cierre', $this->jornada->fresh()->notes);
    }

    public function test_close_recalculates_total(): void
    {
        // The total should be 0 since there are no payments
        $this->jornada->close();

        $this->assertEquals('0.00', $this->jornada->fresh()->total_collected);
    }
}
