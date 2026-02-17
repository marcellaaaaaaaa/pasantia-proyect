<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Puede ser pago parcial — el billing puede tener varios payments
            $table->decimal('amount', 10, 2);

            $table->enum('payment_method', ['cash', 'bank_transfer', 'mobile_payment']);

            // Ciclo de vida del pago:
            // paid               → registrado por el cobrador
            // pending_remittance → en la wallet del cobrador, pendiente de liquidar
            // conciliated        → admin confirmó el ingreso al vault
            // reversed           → anulado
            $table->enum('status', ['paid', 'pending_remittance', 'conciliated', 'reversed'])
                ->default('paid');

            // Referencia bancaria o de transferencia (opcional)
            $table->string('reference', 100)->nullable();

            $table->date('payment_date');
            $table->text('notes')->nullable();

            // Auditoría: cuándo se envió el comprobante al pagador
            $table->timestamp('receipt_sent_at')->nullable();

            $table->timestamps();

            // ─── Índices críticos ───────────────────────────────────────────────

            // Pagos pendientes de remesa por cobrador (consulta del RemittanceService)
            $table->index(['collector_id', 'status'], 'idx_payments_collector_status');

            // Lookup por billing (para calcular saldo pendiente)
            $table->index('billing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
