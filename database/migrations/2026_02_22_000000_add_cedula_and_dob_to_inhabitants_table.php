<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->string('cedula', 20)->nullable();
            $table->date('date_of_birth')->nullable();

            $table->unique(['tenant_id', 'cedula'], 'inhabitants_tenant_cedula_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->dropUnique('inhabitants_tenant_cedula_unique');
            $table->dropColumn(['cedula', 'date_of_birth']);
        });
    }
};
