<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla billing_lines
        Schema::create('billing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['billing_id', 'service_id'], 'uq_billing_line_billing_service');
        });

        // 2. Consolidar billings duplicados por (family_id, period).
        //    Actualmente hay 1 billing por (family_id, service_id, period).
        //    Necesitamos 1 billing por (family_id, period) con N billing_lines.
        if (Schema::hasColumn('billings', 'service_id')) {
            // Para cada grupo (family_id, period), elegir el billing con el menor ID como "canonical"
            // y crear billing_lines para cada servicio apuntando a ese billing canonical.
            DB::statement('
                INSERT INTO billing_lines (billing_id, service_id, amount, created_at, updated_at)
                SELECT canonical.id, b.service_id, b.amount, NOW(), NOW()
                FROM billings b
                INNER JOIN (
                    SELECT MIN(id) AS id, family_id, period
                    FROM billings
                    GROUP BY family_id, period
                ) canonical ON b.family_id = canonical.family_id AND b.period = canonical.period
                WHERE b.service_id IS NOT NULL
            ');

            // Actualizar el monto del billing canonical a la suma de sus líneas
            DB::statement('
                UPDATE billings
                SET amount = (
                    SELECT COALESCE(SUM(billing_lines.amount), 0)
                    FROM billing_lines
                    WHERE billing_lines.billing_id = billings.id
                )
                WHERE id IN (
                    SELECT MIN(id) FROM billings GROUP BY family_id, period
                )
            ');

            // Reasignar los payments de billings duplicados al billing canonical
            DB::statement('
                UPDATE payments
                SET billing_id = canonical.id
                FROM billings b
                INNER JOIN (
                    SELECT MIN(id) AS id, family_id, period
                    FROM billings
                    GROUP BY family_id, period
                ) canonical ON b.family_id = canonical.family_id AND b.period = canonical.period
                WHERE payments.billing_id = b.id
                AND b.id != canonical.id
            ');

            // Eliminar billings duplicados (no-canonical)
            DB::statement('
                DELETE FROM billings
                WHERE id NOT IN (
                    SELECT MIN(id) FROM billings GROUP BY family_id, period
                )
            ');
        }

        // 3. Modificar tabla billings
        Schema::table('billings', function (Blueprint $table) {
            // DROP el unique constraint antiguo
            $table->dropUnique('uq_billing_family_service_period');

            // DROP la FK y columna service_id
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');

            // ADD unique nuevo (family_id, period)
            $table->unique(['family_id', 'period'], 'uq_billing_family_period');
        });
    }

    public function down(): void
    {
        // Restaurar columna service_id en billings
        Schema::table('billings', function (Blueprint $table) {
            $table->dropUnique('uq_billing_family_period');
            $table->foreignId('service_id')->nullable()->after('family_id')->constrained()->cascadeOnDelete();
        });

        // Restaurar datos desde billing_lines
        DB::statement('
            UPDATE billings
            SET service_id = (
                SELECT billing_lines.service_id
                FROM billing_lines
                WHERE billing_lines.billing_id = billings.id
                LIMIT 1
            )
        ');

        // Re-add el unique original
        Schema::table('billings', function (Blueprint $table) {
            $table->unique(['family_id', 'service_id', 'period'], 'uq_billing_family_service_period');
        });

        Schema::dropIfExists('billing_lines');
    }
};
