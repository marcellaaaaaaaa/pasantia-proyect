<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VaultResource\Pages;
use App\Models\CommunityVault;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VaultResource extends Resource
{
    protected static ?string $model = CommunityVault::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Vault';

    protected static ?string $pluralModelLabel = 'Cajas de la comunidad';

    public static function canCreate(): bool
    {
        return false;
    }

    // Sin formulario: recurso de solo lectura
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo actual')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (CommunityVault $record): string =>
                        (float) $record->balance > 0 ? 'success' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Transacciones')
                    ->counts('transactions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última operación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('balance', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaults::route('/'),
            'view'  => Pages\ViewVault::route('/{record}'),
        ];
    }
}
