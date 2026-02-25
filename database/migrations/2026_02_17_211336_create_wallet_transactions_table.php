<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            // Pago que originó este movimiento (crédito por cobro)
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('type', ['credit']);
            $table->decimal('amount', 10, 2);

            // Snapshot del saldo resultante — permite auditar sin recalcular
            $table->decimal('balance_after', 12, 2);

            $table->string('description');

            // Ledger inmutable: solo created_at, sin updated_at
            $table->timestamp('created_at')->useCurrent();

            // ─── Índice crítico ─────────────────────────────────────────────
            // Historial cronológico de una wallet (consulta del estado de cuenta)
            $table->index(['wallet_id', 'created_at'], 'idx_wallet_tx_wallet_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
