<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages;
use App\Models\ExchangeRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Opciones';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Tasa de Cambio';
    protected static ?string $pluralLabel = 'Tasas de Cambio';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tasa BCV del día')->schema([
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Forms\Components\DatePicker::make('date')
                    ->label('Fecha')
                    ->default(today())
                    ->required(),

                Forms\Components\Select::make('currency')
                    ->label('Moneda')
                    ->options(['VED' => 'Bolívares (VED)', 'USD' => 'Dólares (USD)'])
                    ->default('VED')
                    ->required(),

                Forms\Components\TextInput::make('rate_usd')
                    ->label('Unidades por 1 USD')
                    ->numeric()
                    ->minValue(0.0001)
                    ->required()
                    ->helperText('Ejemplo: si 1 USD = 40 VED, ingrese 40'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('rate_usd')
                    ->label('Unidades / USD')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('loader.name')
                    ->label('Cargado por')
                    ->default('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExchangeRates::route('/'),
            'create' => Pages\CreateExchangeRate::route('/create'),
            'edit'   => Pages\EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
