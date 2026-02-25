<?php

namespace App\Filament\Resources\WalletResource\Pages;

use App\Filament\Resources\WalletResource;
use App\Models\Wallet;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewWallet extends ViewRecord
{
    protected static string $resource = WalletResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ── Cabecera ────────────────────────────────────────────────────
                Infolists\Components\Section::make('Cobrador')
                    ->schema([
                        Infolists\Components\TextEntry::make('collector.name')
                            ->label('Nombre'),

                        Infolists\Components\TextEntry::make('collector.email')
                            ->label('Email'),

                        Infolists\Components\TextEntry::make('balance')
                            ->label('Total acumulado')
                            ->money('USD')
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Comunidad')
                            ->visible(fn () => auth()->user()?->isSuperAdmin()),
                    ])
                    ->columns(4),

                // ── Historial de transacciones ───────────────────────────────────
                Infolists\Components\Section::make('Historial de movimientos')
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
                                        $state === 'credit' ? 'Crédito' : 'Débito'
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

                                Infolists\Components\TextEntry::make('description')
                                    ->label('Descripción')
                                    ->columnSpan(2),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
