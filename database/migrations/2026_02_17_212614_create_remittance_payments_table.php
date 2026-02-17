<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-014 — Pivot inmutable remittance_payments.
     *
     * UNIQUE(payment_id) — Riesgo R-2: un pago no puede estar
     * en más de una liquidación.
     */
    public function up(): void
    {
        Schema::create('remittance_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remittance_id')
                  ->constrained('remittances')
                  ->cascadeOnDelete();

            $table->foreignId('payment_id')
                  ->constrained('payments')
                  ->cascadeOnDelete();

            // Solo created_at: este pivot es inmutable
            $table->timestamp('created_at')->useCurrent();

            // R-2: un pago solo puede pertenecer a UNA liquidación
            $table->unique('payment_id', 'uq_remittance_payment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_payments');
    }
};
