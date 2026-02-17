<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->enum('plan', ['free', 'basic', 'pro'])->default('free');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->jsonb('settings')->nullable(); // config por tenant (moneda, timezone)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
