<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->enum('type', ['house', 'apartment', 'commercial']);
            $table->string('unit_number', 20)->nullable(); // para aptos en multifamiliares
            $table->timestamps();

            $table->index(['tenant_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
