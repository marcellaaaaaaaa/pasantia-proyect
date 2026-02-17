<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inhabitants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 200);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            $table->index('family_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inhabitants');
    }
};
