<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // Período de facturación: "2026-02" (YYYY-MM)
            $table->char('period', 7);

            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled', 'void'])
                ->default('pending');
            $table->date('due_date');
            $table->text('notes')->nullable();

            // Trazabilidad: cuándo fue generado este billing
            $table->timestamp('generated_at')->useCurrent();

            $table->timestamps();

            // ─── Índices críticos ───────────────────────────────────────────────

            // R-4: Prevenir billing duplicado para el mismo período/familia/servicio
            $table->unique(['family_id', 'service_id', 'period'], 'uq_billing_family_service_period');

            // Consulta más frecuente: deudas de un tenant en un período por estado
            $table->index(['tenant_id', 'period', 'status'], 'idx_billings_tenant_period_status');

            // Búsqueda de deudas por familia
            $table->index(['family_id', 'period'], 'idx_billings_family_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
