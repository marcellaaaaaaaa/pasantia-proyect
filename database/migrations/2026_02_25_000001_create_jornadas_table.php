<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('collector_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('status', ['open', 'closed'])->default('open');

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->decimal('total_collected', 12, 2)->default(0.00);

            $table->timestamps();

            // Jornadas abiertas por cobrador (para verificar que solo una esté abierta)
            $table->index(['collector_id', 'status'], 'idx_jornadas_collector_status');

            // Jornadas por tenant y estado (panel admin)
            $table->index(['tenant_id', 'status'], 'idx_jornadas_tenant_status');

            // Consultas por fecha de apertura
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornadas');
    }
};
