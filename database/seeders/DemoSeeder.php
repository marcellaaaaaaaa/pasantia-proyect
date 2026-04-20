<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Person;
use App\Models\Property;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Collection;
use App\Models\Jornada;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Sembrando datos de demostración...');

        DB::transaction(function () {
            // ── 1. Tenant ──────────────────────────────────────────────────────
            $tenant = Tenant::create([
                'name'   => 'Urb. Los Pinos',
                'slug'   => 'urb-los-pinos',
                'plan'   => 'pro',
                'status' => 'active',
            ]);
            $this->command->line("  ✓ Tenant: {$tenant->name}");

            // ── 2. Usuarios ────────────────────────────────────────────────────
            $superAdmin = User::create([
                'tenant_id' => null,
                'role'      => 'super_admin',
                'name'      => 'Super Admin',
                'email'     => 'superadmin@demo.com',
                'password'  => bcrypt('password'),
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'role'      => 'admin',
                'name'      => 'Admin Los Pinos',
                'email'     => 'admin@demo.com',
                'password'  => bcrypt('password'),
            ]);

            $collector1 = User::create([
                'tenant_id' => $tenant->id,
                'role'      => 'collector',
                'name'      => 'Juan Cobrador',
                'email'     => 'cobrador@demo.com',
                'password'  => bcrypt('password'),
            ]);

            $collector2 = User::create([
                'tenant_id' => $tenant->id,
                'role'      => 'collector',
                'name'      => 'María Cobrador',
                'email'     => 'cobrador2@demo.com',
                'password'  => bcrypt('password'),
            ]);

            $this->command->line('  ✓ Usuarios creados');

            // ── 3. Sectores (calles) ───────────────────────────────────────────
            $sectorA = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle A', 'description' => 'Sector norte']);
            $sectorB = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle B', 'description' => 'Sector central']);
            $sectorC = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle C', 'description' => 'Sector sur']);

            $collector1->sectors()->attach([$sectorA->id => ['assigned_at' => now()], $sectorB->id => ['assigned_at' => now()]]);
            $collector2->sectors()->attach([$sectorC->id => ['assigned_at' => now()]]);

            // ── 4. Servicios ───────────────────────────────────────────────────
            $svcAgua  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Agua',          'default_price' => 15.00, 'is_active' => true]);
            $svcAseo  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Aseo Urbano',   'default_price' => 10.00, 'is_active' => true]);
            $svcVigl  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Vigilancia',    'default_price' => 30.00, 'is_active' => true]);
            $services = collect([$svcAgua, $svcAseo, $svcVigl]);

            // ── 5. Inmuebles y familias ────────────────────────────────────────
            $casasDatos = [
                [$sectorA, 'Calle A, Casa 1',  'González',  'Ana González',      '0412-1234567'],
                [$sectorA, 'Calle A, Casa 2',  'Rodríguez', 'Carlos Rodríguez',  '0414-2345678'],
                [$sectorB, 'Calle B, Casa 1',  'García',    'Roberto García',    '0414-6789012'],
                [$sectorC, 'Calle C, Apto 1A', 'Castro',    'Fernando Castro',   '0414-4567891'],
            ];

            $families = collect();

            foreach ($casasDatos as [$sector, $address, $lastName, $personName, $phone]) {
                $isApto = str_contains($address, 'Apto');

                $property = Property::create([
                    'tenant_id'   => $tenant->id,
                    'sector_id'   => $sector->id,
                    'address'     => $address,
                    'type'        => $isApto ? 'apartment' : 'house',
                    'unit_number' => $isApto ? substr($address, strrpos($address, ' ') + 1) : null,
                ]);

                $family = Family::create([
                    'tenant_id'   => $tenant->id,
                    'property_id' => $property->id,
                    'name'        => "Familia {$lastName}",
                    'is_active'   => true,
                ]);

                Person::create([
                    'tenant_id'          => $tenant->id,
                    'family_id'          => $family->id,
                    'full_name'          => $personName,
                    'phone'              => $phone,
                    'is_primary_contact' => true,
                ]);

                $family->services()->attach($services->pluck('id'));
                $families->push($family);
            }

            // ── 6. Facturación (Invoices) y Cobros (Collections) ────────────────
            $totalAmount = $services->sum('default_price');
            $vencimientoActual = CarbonImmutable::now()->endOfMonth()->toDateString();

            foreach ($families as $family) {
                // Creamos una factura consolidada para la familia
                $invoice = Invoice::create([
                    'tenant_id'       => $tenant->id,
                    'family_id'       => $family->id,
                    'description'     => 'Mensualidad Servicios Fijos',
                    'amount_usd'      => $totalAmount,
                    'collected_amount_usd' => 0,
                    'status'          => 'pending',
                    'due_date'        => $vencimientoActual,
                ]);
                
                // Familia de la Calle A paga la mitad
                if ($family->property->sector_id === $sectorA->id) {
                    $montoBs = ($totalAmount / 2) * 40; // Tasa 40
                    
                    Collection::create([
                        'tenant_id'        => $tenant->id,
                        'invoice_id'       => $invoice->id,
                        'user_id'          => $collector1->id,
                        'amount'           => $montoBs,
                        'currency'         => 'VED',
                        'exchange_rate'    => 40.00,
                        'amount_usd'       => $totalAmount / 2,
                        'reference_number' => 'REF-' . rand(1000, 9999),
                        'collection_method' => 'mobile_payment',
                        'collection_date'  => now()->toDateString(),
                        'status'           => 'verified',
                    ]);
                    
                    $invoice->update([
                        'collected_amount_usd' => $totalAmount / 2,
                        'status'          => 'partial'
                    ]);
                }
            }

            $this->command->line("  ✓ Familias, personas, facturas y cobros iniciales creados.");
        });
    }
}
