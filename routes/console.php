<?php

use App\Jobs\GenerateMonthlyBillingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Programación de tareas ─────────────────────────────────────────────────

/**
 * INF-007 — Generación automática de cobros mensuales.
 *
 * Se ejecuta el día 1 de cada mes a las 06:00.
 * Despacha GenerateMonthlyBillingsJob en la cola 'billing' para procesar
 * todos los tenants activos en segundo plano sin bloquear el scheduler.
 */
Schedule::call(function () {
    GenerateMonthlyBillingsJob::dispatch(period: now()->format('Y-m'));
})
    ->monthlyOn(1, '06:00')
    ->name('billing:generate-monthly')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
