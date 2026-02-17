<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Aplica el scope globalmente a todos los modelos que tienen tenant_id.
     *
     * - Si hay un tenant activo en el container, filtra por su id.
     * - Si no hay tenant (super_admin o contexto sin auth), no aplica filtro.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if ($tenant !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenant->id);
        }
    }
}
