<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable() // NULL solo para super_admin
                ->constrained()
                ->nullOnDelete()
                ->after('id');

            $table->enum('role', ['super_admin', 'admin', 'collector'])
                ->default('collector')
                ->after('tenant_id');

            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('role');
        });
    }
};
