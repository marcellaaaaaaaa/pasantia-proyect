<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-013 — Liquidaciones (remittances) + FK diferida en wallet_transactions.
     *
     * El campo wallet_transactions.remittance_id fue creado en DB-012 sin FK
     * porque remittances no existía aún. Aquí se añade la constraint.
     */
    public function up(): void
    {
        Schema::create('remittances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->cascadeOnDelete();

            $table->foreignId('collector_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->decimal('amount_declared',  10, 2);
            $table->decimal('amount_confirmed', 10, 2)->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                  ->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('collector_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            // Índice para listar liquidaciones por tenant y estado
            $table->index(['tenant_id', 'status'], 'idx_remittances_tenant_status');
            // Índice para liquidaciones pendientes de un cobrador
            $table->index(['collector_id', 'status'], 'idx_remittances_collector_status');
        });

        // FK diferida: wallet_transactions.remittance_id → remittances.id
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('remittance_id')
                  ->references('id')
                  ->on('remittances')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Eliminar FK diferida antes de dropear la tabla referenciada
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['remittance_id']);
        });

        Schema::dropIfExists('remittances');
    }
};
