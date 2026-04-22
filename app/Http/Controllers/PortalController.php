<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('portal/index', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }

    public function consultar(Tenant $tenant): Response
    {
        return Inertia::render('portal/search', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }

    public function buscar(Request $request, Tenant $tenant): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
        ], [
            'cedula.required' => 'Debes ingresar un número de cédula.',
        ]);

        $person = Person::where('tenant_id', $tenant->id)
            ->where('id_number', trim($request->cedula))
            ->first();

        if (! $person) {
            return back()->withErrors([
                'cedula' => 'No hay una familia registrada con esa cédula en esta comunidad.',
            ]);
        }

        return redirect()->route('portal.familia', [$tenant->slug, $person->family_id]);
    }

    public function familia(Tenant $tenant, Family $family): Response
    {
        if ($family->tenant_id !== $tenant->id) {
            abort(404);
        }

        $family->load([
            'property.sector',
            'people',
            'services',
            'exoneratedServices',
            'invoices' => fn ($q) => $q
                ->with(['service', 'collections'])
                ->orderByDesc('due_date')
                ->limit(30),
        ]);

        $family->invoices->each->append('balance_usd');

        return Inertia::render('portal/family', [
            'tenant'     => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'family'     => $family,
            'is_solvent' => $family->isSolvent(),
        ]);
    }
}
