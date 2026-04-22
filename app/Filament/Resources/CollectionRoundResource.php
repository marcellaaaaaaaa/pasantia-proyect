<?php

namespace App\Filament\Resources;

use App\Application\Billing\Services\InvoicingService;
use App\Filament\Resources\CollectionRoundResource\Pages;
use App\Models\CollectionRound;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionRoundResource extends Resource
{
    protected static ?string $model = CollectionRound::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Gestión de Cobros';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Jornada de Cobro';
    protected static ?string $pluralLabel = 'Jornadas de Cobro';

    public static function form(Form $form): Form
    {
        $hasServiceOfType = function (Forms\Get $get, string $type): bool {
            $value = $get('services');
            if (empty($value)) {
                return false;
            }
            $ids = is_array($value) ? $value : [$value];
            return \App\Models\Service::whereIn('id', $ids)->where('type', $type)->exists();
        };

        $tenantIdForFilter = fn (Forms\Get $get): ?int =>
            ($get('tenant_id') ? (int) $get('tenant_id') : auth()->user()?->tenant_id);

        return $form->schema([
            Forms\Components\Section::make('Sectores y Servicios')
                ->description('Define qué sectores y servicios cubre esta jornada.')
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Comunidad')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('sectors', []);
                            $set('services', null);
                        })
                        ->visible(fn () => auth()->user()?->isSuperAdmin())
                        ->columnSpanFull(),

                    Forms\Components\Select::make('sectors')
                        ->label('Sectores')
                        ->relationship(
                            'sectors',
                            'name',
                            fn ($query, Forms\Get $get) => $tenantIdForFilter($get)
                                ? $query->where('tenant_id', $tenantIdForFilter($get))
                                : $query
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('services')
                        ->label('Servicio de la Jornada')
                        ->relationship(
                            'services',
                            'name',
                            fn ($query, Forms\Get $get) => $tenantIdForFilter($get)
                                ? $query->where('is_active', true)->where('tenant_id', $tenantIdForFilter($get))
                                : $query->where('is_active', true)
                        )
                        ->multiple()
                        ->maxItems(1)
                        ->preload()
                        ->searchable()
                        ->required()
                        ->live()
                        ->helperText('Elige el servicio que cubre esta jornada.'),
                ])->columns(2),

            Forms\Components\Section::make('Datos de la Jornada')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de la Jornada')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('collector_id')
                    ->label('Cobrador Responsable')
                    ->relationship('collector', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'open'      => 'Abierta',
                        'closed'    => 'Cerrada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->default('open')
                    ->required()
                    ->hiddenOn('create'),

                Forms\Components\Select::make('billing_period')
                    ->label('Periodo de Facturación')
                    ->options([
                        'weekly'    => 'Semanal',
                        'quincenal' => 'Quincenal (15 y fin de mes)',
                        'monthly'   => 'Mensual',
                    ])
                    ->required()
                    ->visible(fn (Forms\Get $get) => $hasServiceOfType($get, 'fixed'))
                    ->helperText('Se aplica a los servicios fijos incluidos en la jornada.'),

                Forms\Components\DateTimePicker::make('opened_at')
                    ->label('Apertura')
                    ->default(now()),

                Forms\Components\DatePicker::make('closed_at')
                    ->label('Cierre')
                    ->required(fn (Forms\Get $get) => $hasServiceOfType($get, 'jornada'))
                    ->visible(fn (Forms\Get $get) => $hasServiceOfType($get, 'jornada'))
                    ->helperText('Fecha en que cierra la jornada puntual.'),
            ])->columns(2),

            Forms\Components\Section::make('Notas')->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Jornada')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'      => 'success',
                        'closed'    => 'gray',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open'      => 'Abierta',
                        'closed'    => 'Cerrada',
                        'cancelled' => 'Cancelada',
                    }),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Facturas')
                    ->counts('invoices')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_collected_usd')
                    ->label('Recaudado ($)')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('opened_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('generateInvoices')
                    ->label('Generar Facturas')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (CollectionRound $record): bool =>
                        $record->status === 'open' && $record->invoices()->doesntExist()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Generar Facturas para la Jornada')
                    ->modalDescription('Se crearán facturas para todas las familias activas de los sectores seleccionados. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, generar facturas')
                    ->action(function (CollectionRound $record): void {
                        try {
                            $count = app(InvoicingService::class)->generateInvoicesForRound($record);

                            Notification::make()
                                ->title("{$count} facturas generadas correctamente")
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('No se pudieron generar facturas')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCollectionRounds::route('/'),
            'create' => Pages\CreateCollectionRound::route('/create'),
            'edit'   => Pages\EditCollectionRound::route('/{record}/edit'),
        ];
    }
}
