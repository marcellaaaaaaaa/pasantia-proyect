<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornada_sector', function (Blueprint $table) {
            $table->id();

            $table->foreignId('jornada_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('sector_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique(['jornada_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornada_sector');
    }
};
