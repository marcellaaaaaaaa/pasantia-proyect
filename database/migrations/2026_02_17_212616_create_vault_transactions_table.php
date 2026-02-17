<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-016 — Ledger del vault central (inmutable, igual que wallet_transactions).
     *
     * Sin updated_at: los registros del ledger no pueden modificarse.
     */
    public function up(): void
    {
        Schema::create('vault_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vault_id')
                  ->constrained('community_vaults')
                  ->cascadeOnDelete();

            $table->foreignId('remittance_id')
                  ->constrained('remittances')
                  ->cascadeOnDelete();

            $table->enum('type', ['credit', 'debit']);

            $table->decimal('amount',        10, 2);
            $table->decimal('balance_after', 12, 2);

            $table->string('description', 255);

            // Solo created_at: ledger inmutable
            $table->timestamp('created_at')->useCurrent();

            // Ledger ordenado cronológicamente por vault
            $table->index(['vault_id', 'created_at'], 'idx_vault_tx_vault_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_transactions');
    }
};
