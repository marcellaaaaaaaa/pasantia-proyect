<?php

namespace App\Filament\Resources\VaultResource\Pages;

use App\Filament\Resources\VaultResource;
use App\Models\CommunityVault;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewVault extends ViewRecord
{
    protected static string $resource = VaultResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ── Cabecera ────────────────────────────────────────────────────
                Infolists\Components\Section::make('Caja central')
                    ->schema([
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Comunidad'),

                        Infolists\Components\TextEntry::make('balance')
                            ->label('Saldo actual')
                            ->money('USD')
                            ->weight('bold')
                            ->color(fn (CommunityVault $record): string =>
                                (float) $record->balance > 0 ? 'success' : 'gray'
                            ),

                        Infolists\Components\TextEntry::make('transactions_count')
                            ->label('Total de movimientos')
                            ->state(fn (CommunityVault $record) => $record->transactions()->count()),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Última actualización')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(4),

                // ── Historial de transacciones ───────────────────────────────────
                Infolists\Components\Section::make('Historial de ingresos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('transactions')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->dateTime('d/m/Y H:i'),

                                Infolists\Components\TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->color(fn (string $state): string =>
                                        $state === 'credit' ? 'success' : 'danger'
                                    )
                                    ->formatStateUsing(fn (string $state): string =>
                                        $state === 'credit' ? 'Ingreso' : 'Egreso'
                                    ),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('USD')
                                    ->color(fn ($record): string =>
                                        $record->type === 'credit' ? 'success' : 'danger'
                                    ),

                                Infolists\Components\TextEntry::make('balance_after')
                                    ->label('Saldo resultante')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('remittance.collector.name')
                                    ->label('Cobrador')
                                    ->placeholder('—'),

                                Infolists\Components\TextEntry::make('description')
                                    ->label('Descripción')
                                    ->columnSpan(2),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
