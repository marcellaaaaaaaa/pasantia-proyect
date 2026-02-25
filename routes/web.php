<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// ── Comprobantes de pago (URL firmada, sin autenticación, expira en 48h) ──
Route::get('/receipts/{payment}', function (\App\Models\Payment $payment) {
    return app(\App\Services\ReceiptService::class)
        ->generate($payment)
        ->stream("comprobante-{$payment->id}.pdf");
})->name('receipts.show')->middleware('signed');

// ── Rutas del Cobrador (PWA + Inertia) ────────────────────────────────────────
Route::middleware(['auth', 'verified', \App\Http\Middleware\SetTenantScope::class])
    ->prefix('collector')
    ->name('collector.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CollectorController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/billing/{billing}', [\App\Http\Controllers\Api\CollectorController::class, 'showBilling'])
            ->name('billing');

        Route::post('/billing/{billing}', [\App\Http\Controllers\Api\CollectorController::class, 'pay'])
            ->name('billing.pay');

        Route::get('/jornadas', [\App\Http\Controllers\Api\CollectorController::class, 'jornadaPage'])
            ->name('jornadas');

        Route::post('/jornadas/open', [\App\Http\Controllers\Api\CollectorController::class, 'openJornada'])
            ->name('jornadas.open');

        Route::post('/jornadas/{jornada}/close', [\App\Http\Controllers\Api\CollectorController::class, 'closeJornada'])
            ->name('jornadas.close');
    });

// ── API: sincronización de pagos offline (JSON) ───────────────────────────────
Route::middleware(['auth'])
    ->post('/api/collector/payments/sync', [\App\Http\Controllers\Api\CollectorController::class, 'sync'])
    ->name('collector.payments.sync');

require __DIR__.'/settings.php';
