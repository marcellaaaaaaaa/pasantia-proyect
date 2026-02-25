<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletResource\Pages;
use App\Models\Wallet;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Billetera';

    protected static ?string $pluralModelLabel = 'Billeteras de cobradores';

    /** Solo admin y super_admin acceden. Los cobradores usan la PWA. */
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
                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('collector.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Total acumulado')
                    ->money('USD')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Transacciones')
                    ->counts('transactions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última operación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Filters\Filter::make('con_saldo')
                    ->label('Solo con saldo > 0')
                    ->query(fn ($query) => $query->where('balance', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('balance', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallets::route('/'),
            'view'  => Pages\ViewWallet::route('/{record}'),
        ];
    }
}
