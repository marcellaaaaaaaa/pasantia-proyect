<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;

    protected $fillable = ['tenant_id', 'date', 'currency', 'rate_usd', 'loaded_by'];

    protected $casts = [
        'date' => 'date',
        'rate_usd' => 'decimal:4',
    ];

    public function loader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    /**
     * Devuelve la tasa del día para el tenant activo.
     * Útil en formularios Filament para auto-poblar el campo.
     */
    public static function forToday(?int $tenantId = null, string $currency = 'VED'): ?self
    {
        $tenantId ??= auth()->user()?->tenant_id;

        return static::where('tenant_id', $tenantId)
            ->where('date', today())
            ->where('currency', $currency)
            ->first();
    }

    /**
     * Tasa más reciente disponible (fallback cuando no hay tasa de hoy).
     */
    public static function latest(?int $tenantId = null, string $currency = 'VED'): ?self
    {
        $tenantId ??= auth()->user()?->tenant_id;

        return static::where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->orderByDesc('date')
            ->first();
    }
}
