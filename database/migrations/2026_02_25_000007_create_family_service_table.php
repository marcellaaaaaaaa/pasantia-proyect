<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['family_id', 'service_id']);
        });

        // Poblar: asignar todos los servicios activos del tenant a cada familia activa
        $families = DB::table('families')->where('is_active', true)->get();

        foreach ($families as $family) {
            $serviceIds = DB::table('services')
                ->where('tenant_id', $family->tenant_id)
                ->where('is_active', true)
                ->pluck('id');

            $rows = $serviceIds->map(fn ($serviceId) => [
                'family_id'  => $family->id,
                'service_id' => $serviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($rows)) {
                DB::table('family_service')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_service');
    }
};
