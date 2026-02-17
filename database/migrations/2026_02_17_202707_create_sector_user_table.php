<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            // Un cobrador no puede estar asignado dos veces al mismo sector
            $table->unique(['user_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_user');
    }
};
