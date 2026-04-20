<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('default_price_usd', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('collection_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('open'); // open, closed, cancelled
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('total_collected_usd', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collection_round_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount_usd', 10, 2);
            $table->decimal('collected_amount_usd', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending, partial, collected, cancelled
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2); // local amount
            $table->char('currency', 3)->default('VED');
            $table->decimal('exchange_rate', 10, 4)->default(1);
            $table->decimal('amount_usd', 10, 2);
            $table->string('method')->default('mobile_payment'); // cash, transfer, mobile_payment
            $table->string('reference')->nullable();
            $table->date('collected_at');
            $table->string('status')->default('verified'); // pending, verified, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Many-to-Many for Recurrent Services
        Schema::create('family_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Many-to-Many for Collection Rounds (which sectors/services are included)
        Schema::create('collection_round_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('collection_round_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_round_service');
        Schema::dropIfExists('collection_round_sector');
        Schema::dropIfExists('family_service');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('collection_rounds');
        Schema::dropIfExists('services');
    }
};
