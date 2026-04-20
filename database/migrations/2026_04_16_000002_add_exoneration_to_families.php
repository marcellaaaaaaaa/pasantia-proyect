<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->boolean('is_exonerated')->default(false)->after('is_active');
            $table->string('exoneration_reason')->nullable()->after('is_exonerated');
            $table->foreignId('exonerated_by')->nullable()->constrained('users')->nullOnDelete()->after('exoneration_reason');
            $table->timestamp('exonerated_at')->nullable()->after('exonerated_by');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign(['exonerated_by']);
            $table->dropColumn(['is_exonerated', 'exoneration_reason', 'exonerated_by', 'exonerated_at']);
        });
    }
};
