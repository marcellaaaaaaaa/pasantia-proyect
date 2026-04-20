<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->char('currency', 3)->default('VED');
            $table->decimal('rate_usd', 14, 4)->comment('Unidades de la moneda por 1 USD');
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'currency']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Permite deduplicación robusta sin depender del texto de description
            $table->string('period_month', 7)->nullable()->after('description')
                ->comment('Formato YYYY-MM. Nulo para facturas de jornadas.');

            $table->unique(['tenant_id', 'family_id', 'period_month'], 'invoices_monthly_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_monthly_unique');
            $table->dropColumn('period_month');
        });

        Schema::dropIfExists('exchange_rates');
    }
};
