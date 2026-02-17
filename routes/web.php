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

require __DIR__.'/settings.php';
