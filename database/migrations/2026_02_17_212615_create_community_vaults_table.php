<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-015 — Caja central de la comunidad.
     *
     * UNIQUE(tenant_id) — cada tenant tiene exactamente UN vault.
     * Se crea automáticamente al aprobar la primera remittance.
     */
    public function up(): void
    {
        Schema::create('community_vaults', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                  ->unique()
                  ->constrained('tenants')
                  ->cascadeOnDelete();

            $table->decimal('balance', 12, 2)->default(0.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_vaults');
    }
};
