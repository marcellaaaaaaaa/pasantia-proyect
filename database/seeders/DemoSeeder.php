<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Family;
use App\Models\Inhabitant;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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
                'tenant_id' => null,               // super_admin no pertenece a un tenant
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

            $this->command->line('  ✓ Usuarios: superadmin@demo.com | admin@demo.com | cobrador@demo.com | cobrador2@demo.com (pass: password)');

            // ── 3. Sectores (calles) ───────────────────────────────────────────
            $sectorA = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle A', 'description' => 'Sector norte de la urbanización']);
            $sectorB = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle B', 'description' => 'Sector central']);
            $sectorC = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Calle C', 'description' => 'Sector sur']);

            // Asignar cobradores a sectores
            $collector1->sectors()->attach([
                $sectorA->id => ['assigned_at' => now()],
                $sectorB->id => ['assigned_at' => now()],
            ]);
            $collector2->sectors()->attach([
                $sectorC->id => ['assigned_at' => now()],
            ]);

            $this->command->line('  ✓ Sectores: Calle A, B, C — Juan → A+B, María → C');

            // ── 4. Servicios ───────────────────────────────────────────────────
            $svcAgua  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Agua',          'default_price' => 15.00, 'is_active' => true]);
            $svcAseo  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Aseo Urbano',   'default_price' => 10.00, 'is_active' => true]);
            $svcVigl  = Service::create(['tenant_id' => $tenant->id, 'name' => 'Vigilancia',    'default_price' => 30.00, 'is_active' => true]);
            $services = [$svcAgua, $svcAseo, $svcVigl];

            $this->command->line('  ✓ Servicios: Agua ($15), Aseo ($10), Vigilancia ($30)');

            // ── 5. Inmuebles y familias ────────────────────────────────────────
            $casasDatos = [
                // [sector, dirección, apellido del jefe de familia]
                [$sectorA, 'Calle A, Casa 1',  'González',  'Ana González',      '0412-1234567'],
                [$sectorA, 'Calle A, Casa 2',  'Rodríguez', 'Carlos Rodríguez',  '0414-2345678'],
                [$sectorA, 'Calle A, Casa 3',  'López',     'María López',       '0416-3456789'],
                [$sectorA, 'Calle A, Casa 4',  'Martínez',  'Pedro Martínez',    '0424-4567890'],
                [$sectorA, 'Calle A, Casa 5',  'Hernández', 'Luisa Hernández',   '0412-5678901'],
                [$sectorB, 'Calle B, Casa 1',  'García',    'Roberto García',    '0414-6789012'],
                [$sectorB, 'Calle B, Casa 2',  'Pérez',     'Carmen Pérez',      '0416-7890123'],
                [$sectorB, 'Calle B, Casa 3',  'Torres',    'José Torres',       '0424-8901234'],
                [$sectorB, 'Calle B, Casa 4',  'Flores',    'Elena Flores',      '0412-9012345'],
                [$sectorC, 'Calle C, Casa 1',  'Vargas',    'Miguel Vargas',     '0414-0123456'],
                [$sectorC, 'Calle C, Casa 2',  'Morales',   'Sofía Morales',     '0416-1234568'],
                [$sectorC, 'Calle C, Casa 3',  'Reyes',     'Andrés Reyes',      '0424-2345679'],
                [$sectorC, 'Calle C, Casa 4',  'Jiménez',   'Patricia Jiménez',  '0412-3456780'],
                [$sectorC, 'Calle C, Apto 1A', 'Castro',    'Fernando Castro',   '0414-4567891'],
                [$sectorC, 'Calle C, Apto 1B', 'Ramos',     'Valentina Ramos',   '0416-5678902'],
            ];

            $families = collect();

            foreach ($casasDatos as [$sector, $address, $lastName, $inhabitantName, $phone]) {
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

                Inhabitant::create([
                    'tenant_id'          => $tenant->id,
                    'family_id'          => $family->id,
                    'full_name'          => $inhabitantName,
                    'phone'              => $phone,
                    'email'              => null,
                    'is_primary_contact' => true,
                ]);

                $families->push($family);
            }

            $this->command->line("  ✓ {$families->count()} familias con inmuebles e habitantes");

            // ── 6. Billings ────────────────────────────────────────────────────
            $periodoActual  = CarbonImmutable::now()->format('Y-m');        // 2026-02
            $periodoAnterior = CarbonImmutable::now()->subMonth()->format('Y-m'); // 2026-01
            $vencimientoActual   = CarbonImmutable::now()->endOfMonth()->toDateString();
            $vencimientoAnterior = CarbonImmutable::now()->subMonth()->endOfMonth()->toDateString();

            $billingsPeriodoAnterior = collect();
            $billingsPeriodoActual   = collect();

            foreach ($families as $family) {
                foreach ($services as $service) {
                    // Billing del mes anterior (ya vencido)
                    $billingsPeriodoAnterior->push(Billing::create([
                        'tenant_id'    => $tenant->id,
                        'family_id'    => $family->id,
                        'service_id'   => $service->id,
                        'period'       => $periodoAnterior,
                        'amount'       => $service->default_price,
                        'status'       => 'pending',
                        'due_date'     => $vencimientoAnterior,
                        'generated_at' => CarbonImmutable::now()->subMonth()->startOfMonth(),
                    ]));

                    // Billing del mes actual
                    $billingsPeriodoActual->push(Billing::create([
                        'tenant_id'    => $tenant->id,
                        'family_id'    => $family->id,
                        'service_id'   => $service->id,
                        'period'       => $periodoActual,
                        'amount'       => $service->default_price,
                        'status'       => 'pending',
                        'due_date'     => $vencimientoActual,
                        'generated_at' => CarbonImmutable::now()->startOfMonth(),
                    ]));
                }
            }

            $totalBillings = $billingsPeriodoAnterior->count() + $billingsPeriodoActual->count();
            $this->command->line("  ✓ {$totalBillings} billings generados ({$periodoAnterior} + {$periodoActual})");

            // ── 7. Pagos demo — Juan cobra 8 billings del mes anterior ─────────
            //    Status: pending_remittance → en su wallet esperando liquidación
            $walletJuan = Wallet::create([
                'tenant_id' => $tenant->id,
                'user_id'   => $collector1->id,
                'balance'   => '0.00',
            ]);

            // Tomar billings del mes anterior de familias en Calle A y B (sector de Juan)
            $familiasJuan = $families->filter(function ($f) use ($sectorA, $sectorB) {
                return in_array($f->property->sector_id, [$sectorA->id, $sectorB->id]);
            });

            $paymentsJuan = collect();
            $balanceJuan  = '0.00';

            // Marcar 6 billings del periodo anterior como pagados (pago completo)
            $billingsPagadosJuan = $billingsPeriodoAnterior
                ->filter(fn ($b) => $familiasJuan->pluck('id')->contains($b->family_id))
                ->take(6);

            foreach ($billingsPagadosJuan as $billing) {
                $amount = (float) $billing->amount;

                $payment = Payment::create([
                    'tenant_id'      => $tenant->id,
                    'billing_id'     => $billing->id,
                    'collector_id'   => $collector1->id,
                    'amount'         => $amount,
                    'payment_method' => 'cash',
                    'status'         => 'pending_remittance',
                    'payment_date'   => CarbonImmutable::now()->subDays(rand(3, 10))->toDateString(),
                ]);

                $billing->update(['status' => 'paid']);
                $balanceJuan  = bcadd($balanceJuan, (string) $amount, 2);
                $paymentsJuan->push($payment);

                WalletTransaction::create([
                    'wallet_id'     => $walletJuan->id,
                    'payment_id'    => $payment->id,
                    'type'          => 'credit',
                    'amount'        => $amount,
                    'balance_after' => $balanceJuan,
                    'description'   => "Cobro billing #{$billing->id}",
                ]);
            }

            $walletJuan->update(['balance' => $balanceJuan]);

            // Wallet de María (sin pagos aún)
            Wallet::create([
                'tenant_id' => $tenant->id,
                'user_id'   => $collector2->id,
                'balance'   => '0.00',
            ]);

            $this->command->line("  ✓ {$paymentsJuan->count()} pagos en pending_remittance para Juan (wallet: \${$balanceJuan})");

            // ── 8. Resumen final ───────────────────────────────────────────────
            $this->command->newLine();
            $this->command->line('  ┌─────────────────────────────────────────────┐');
            $this->command->line('  │           CREDENCIALES DE ACCESO            │');
            $this->command->line('  ├─────────────────────────────────────────────┤');
            $this->command->line('  │ Super Admin  superadmin@demo.com            │');
            $this->command->line('  │ Admin        admin@demo.com                 │');
            $this->command->line('  │ Cobrador 1   cobrador@demo.com  (Calle A+B) │');
            $this->command->line('  │ Cobrador 2   cobrador2@demo.com (Calle C)   │');
            $this->command->line('  │ Contraseña   password                       │');
            $this->command->line('  └─────────────────────────────────────────────┘');
            $this->command->line("  Panel admin: http://localhost:8080/admin");
            $this->command->line("  PWA cobrador: http://localhost:8080/collector");
        });
    }
}
