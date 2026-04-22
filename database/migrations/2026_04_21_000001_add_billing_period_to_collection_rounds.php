<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_rounds', function (Blueprint $table) {
            $table->string('billing_period')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('collection_rounds', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
