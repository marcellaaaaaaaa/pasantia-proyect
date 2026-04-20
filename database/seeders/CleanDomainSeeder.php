<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Sector;
use App\Models\Property;
use App\Models\Family;
use App\Models\Person;
use App\Models\Service;
use Illuminate\Database\Seeder;

class CleanDomainSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin (No Tenant)
        User::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@demo.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        // 2. Demo Community
        $tenant = Tenant::create(['name' => 'Demo Community', 'slug' => 'demo', 'status' => 'active']);

        // 3. Community Admin
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Community Admin',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $sector = Sector::create(['tenant_id' => $tenant->id, 'name' => 'Main Street']);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'sector_id' => $sector->id,
            'address' => 'House #1',
            'type' => 'house',
        ]);

        $family = Family::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'name' => 'Doe Family',
        ]);

        Person::create([
            'tenant_id' => $tenant->id,
            'family_id' => $family->id,
            'full_name' => 'John Doe',
            'is_primary_contact' => true,
        ]);

        Service::create(['tenant_id' => $tenant->id, 'name' => 'Water', 'default_price_usd' => 10.00]);
        Service::create(['tenant_id' => $tenant->id, 'name' => 'Trash', 'default_price_usd' => 5.00]);
    }
}
