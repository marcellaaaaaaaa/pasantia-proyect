<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectionResource\Pages;
use App\Models\Collection;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gestión de Cobros';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Cobro';
    protected static ?string $pluralLabel = 'Cobros';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detalle del Cobro')->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Factura')
                    ->options(function () {
                        return Invoice::with('family')
                            ->whereIn('status', ['pending', 'partial'])
                            ->get()
                            ->mapWithKeys(fn (Invoice $i) => [
                                $i->id => "[#{$i->id}] {$i->family?->name} — {$i->description} (Saldo: \${$i->balance_usd})",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if (! $state) return;
                        $invoice = Invoice::find($state);
                        if ($invoice) {
                            $set('_max_usd', (float) $invoice->balance_usd);
                        }
                    }),

                // Campo oculto para el saldo máximo permitido
                Forms\Components\Hidden::make('_max_usd')->default(0),

                Forms\Components\Select::make('currency')
                    ->label('Moneda')
                    ->options(['VED' => 'Bolívares (VED)', 'USD' => 'Dólares (USD)'])
                    ->default('VED')
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('exchange_rate')
                    ->label('Tasa BCV')
                    ->numeric()
                    ->default(function () {
                        $rate = ExchangeRate::forToday();
                        return $rate ? (float) $rate->rate_usd : null;
                    })
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn (Forms\Get $get) => $get('currency') === 'VED')
                    ->helperText(function () {
                        $rate = ExchangeRate::forToday();
                        if ($rate) {
                            return "Tasa del día ({$rate->date->format('d/m/Y')}): {$rate->rate_usd}";
                        }
                        return '⚠ No hay tasa cargada para hoy. Contacte al administrador.';
                    }),

                Forms\Components\TextInput::make('amount')
                    ->label('Monto Cobrado')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->live(debounce: 400)
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $currency     = $get('currency');
                        $exchangeRate = (float) ($get('exchange_rate') ?: 1);

                        $usd = $currency === 'USD'
                            ? round((float) $state, 2)
                            : round((float) $state / $exchangeRate, 2);

                        $set('amount_usd', $usd);
                    }),

                Forms\Components\TextInput::make('amount_usd')
                    ->label('Equivalente ($)')
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\Select::make('method')
                    ->label('Método de Pago')
                    ->options([
                        'cash'           => 'Efectivo',
                        'transfer'       => 'Transferencia',
                        'mobile_payment' => 'Pago Móvil',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('reference')
                    ->label('Referencia / Número de Operación')
                    ->maxLength(100),

                Forms\Components\DatePicker::make('collected_at')
                    ->label('Fecha del Cobro')
                    ->default(today())
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.family.name')
                    ->label('Familia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.description')
                    ->label('Factura')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn ($state, $record) => "{$state} {$record->currency}"),

                Tables\Columns\TextColumn::make('amount_usd')
                    ->label('Equivalente ($)')
                    ->money('USD'),

                Tables\Columns\TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash'           => 'Efectivo',
                        'transfer'       => 'Transferencia',
                        'mobile_payment' => 'Pago Móvil',
                        default          => $state,
                    }),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('collected_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('collected_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit'   => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}
