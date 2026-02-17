<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantScope
{
    /**
     * Inyecta el tenant activo en el container de la aplicación.
     *
     * - super_admin (tenant_id = null): no inyecta nada → ve todos los tenants.
     * - admin / collector: inyecta su tenant → TenantScope filtra automáticamente.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id !== null) {
            $tenant = Tenant::findOrFail($user->tenant_id);
            app()->instance('current_tenant', $tenant);
        }

        return $next($request);
    }
}
