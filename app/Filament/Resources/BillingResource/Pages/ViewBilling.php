<?php

namespace App\Filament\Resources\BillingResource\Pages;

use App\Filament\Resources\BillingResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBilling extends ViewRecord
{
    protected static string $resource = BillingResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Datos del cobro')
                    ->schema([
                        Infolists\Components\TextEntry::make('family.name')
                            ->label('Familia'),

                        Infolists\Components\TextEntry::make('period')
                            ->label('Período'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending'   => 'warning',
                                'paid'      => 'success',
                                'cancelled' => 'gray',
                                'void'      => 'danger',
                                default     => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending'   => 'Pendiente',
                                'paid'      => 'Cobrado',
                                'cancelled' => 'Cancelado',
                                'void'      => 'Anulado',
                                default     => $state,
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Servicios incluidos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lines')
                            ->schema([
                                Infolists\Components\TextEntry::make('service.name')
                                    ->label('Servicio'),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('USD'),
                            ])
                            ->columns(2),
                    ]),

                Infolists\Components\Section::make('Montos')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Total')
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('amount_paid')
                            ->label('Pagado')
                            ->state(fn ($record) => $record->amount_paid)
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('amount_pending')
                            ->label('Pendiente')
                            ->state(fn ($record) => $record->amount_pending)
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Vencimiento')
                            ->date('d/m/Y'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pagos registrados')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Fecha')
                                    ->date('d/m/Y'),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Método')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'cash'           => 'Efectivo',
                                        'bank_transfer'  => 'Transferencia',
                                        'mobile_payment' => 'Pago móvil',
                                        default          => $state,
                                    }),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid'     => 'success',
                                        'reversed' => 'danger',
                                        default    => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'paid'     => 'Pagado',
                                        'reversed' => 'Anulado',
                                        default    => $state,
                                    }),

                                Infolists\Components\TextEntry::make('collector.name')
                                    ->label('Cobrador'),

                                Infolists\Components\TextEntry::make('reference')
                                    ->label('Referencia')
                                    ->placeholder('—'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
