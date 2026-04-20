<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletResource\Pages;
use App\Models\Wallet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;
    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?string $label = 'Billetera';
    protected static ?string $pluralLabel = 'Billeteras';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('balance_usd')->label('Saldo ($)')->numeric()->prefix('$')->disabled(),
            Forms\Components\Select::make('owner_type')->label('Tipo de Dueño')->options(['App\Models\Family' => 'Familia', 'App\Models\User' => 'Cobrador'])->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('owner.name')->label('Dueño')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('balance_usd')->label('Saldo ($)')->money('USD')->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->label('Último Movimiento')->dateTime(),
        ])->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWallets::route('/'), 'view' => Pages\ViewWallet::route('/{record}')];
    }
}
