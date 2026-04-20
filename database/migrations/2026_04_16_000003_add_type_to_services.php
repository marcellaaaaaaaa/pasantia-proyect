<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // fixed = se asigna a familias, genera factura mensual automática
            // jornada = se cobra puntualmente a través de una jornada (CLAP, gas, etc.)
            $table->string('type')->default('fixed')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
