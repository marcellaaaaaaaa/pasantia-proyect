<?php

namespace App\Application\Billing\Services;

use App\Models\ExchangeRate;

class ExchangeRateService
{
    /**
     * Devuelve la tasa activa del día para una moneda.
     * Retorna null si aún no se ha cargado la tasa del día.
     */
    public function forToday(int $tenantId, string $currency = 'VED'): ?ExchangeRate
    {
        return ExchangeRate::where('tenant_id', $tenantId)
            ->where('date', today())
            ->where('currency', $currency)
            ->first();
    }

    /**
     * Indica si ya existe tasa cargada para hoy.
     */
    public function hasTodayRate(int $tenantId, string $currency = 'VED'): bool
    {
        return $this->forToday($tenantId, $currency) !== null;
    }

    /**
     * Tasa más reciente disponible (para mostrar como referencia en alertas).
     */
    public function latest(int $tenantId, string $currency = 'VED'): ?ExchangeRate
    {
        return ExchangeRate::where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->orderByDesc('date')
            ->first();
    }
}
