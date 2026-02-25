<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JornadaResource\Pages;
use App\Models\Jornada;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JornadaResource extends Resource
{
    protected static ?string $model = Jornada::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Cobros';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Jornada';

    protected static ?string $pluralModelLabel = 'Jornadas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->label('Comunidad')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin())
                ->live(),

            Forms\Components\Select::make('collector_id')
                ->label('Cobrador')
                ->relationship(
                    'collector',
                    'name',
                    modifyQueryUsing: function (Builder $query, Get $get) {
                        $query->where('role', User::ROLE_COLLECTOR);

                        if (auth()->user()?->isSuperAdmin() && $get('tenant_id')) {
                            $query->where('tenant_id', $get('tenant_id'));
                        }

                        return $query;
                    },
                )
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\Select::make('sectors')
                ->label('Calles / Sectores')
                ->multiple()
                ->relationship(
                    name: 'sectors',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query, Get $get) {
                        $tenantId = auth()->user()?->isSuperAdmin()
                            ? $get('tenant_id')
                            : auth()->user()?->tenant_id;

                        return $query->withoutGlobalScopes()
                            ->when(
                                $tenantId,
                                fn (Builder $q) => $q->where('tenant_id', $tenantId)
                            );
                    }
                )
                ->preload()
                ->required(),

            Forms\Components\Select::make('services')
                ->label('Servicios a cobrar')
                ->multiple()
                ->relationship(
                    name: 'services',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query, Get $get) {
                        $tenantId = auth()->user()?->isSuperAdmin()
                            ? $get('tenant_id')
                            : auth()->user()?->tenant_id;

                        return $query->withoutGlobalScopes()
                            ->where('is_active', true)
                            ->when(
                                $tenantId,
                                fn (Builder $q) => $q->where('tenant_id', $tenantId)
                            );
                    }
                )
                ->preload()
                ->required(),

            Forms\Components\DateTimePicker::make('opened_at')
                ->label('Apertura')
                ->required()
                ->default(now())
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Set $set, $state) => $set(
                    'closed_at',
                    $state ? \Carbon\Carbon::parse($state)->addMonth() : null,
                )),

            Forms\Components\DateTimePicker::make('closed_at')
                ->label('Cierre')
                ->default(now()->addMonth()),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('N° Jornada')
                    ->sortable(),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sectors.name')
                    ->label('Calles')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('services.name')
                    ->label('Servicios')
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->limitList(3)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'   => 'warning',
                        'closed' => 'success',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open'   => 'Abierta',
                        'closed' => 'Cerrada',
                        default  => $state,
                    }),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_collected')
                    ->label('Total cobrado')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Pagos')
                    ->counts('payments')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccione')
                    ->options([
                        'open'   => 'Abierta',
                        'closed' => 'Cerrada',
                    ]),

                Tables\Filters\SelectFilter::make('collector')
                    ->label('Cobrador')
                    ->relationship('collector', 'name')
                    ->placeholder('Seleccione')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->placeholder('Seleccione')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('opened_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Datos de la Jornada')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('N° Jornada'),

                        Infolists\Components\TextEntry::make('collector.name')
                            ->label('Cobrador'),

                        Infolists\Components\TextEntry::make('sectors.name')
                            ->label('Calles / Sectores')
                            ->badge()
                            ->separator(', '),

                        Infolists\Components\TextEntry::make('services.name')
                            ->label('Servicios a cobrar')
                            ->badge()
                            ->color('info')
                            ->separator(', '),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open'   => 'warning',
                                'closed' => 'success',
                                default  => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'open'   => 'Abierta',
                                'closed' => 'Cerrada',
                                default  => $state,
                            }),

                        Infolists\Components\TextEntry::make('total_collected')
                            ->label('Total cobrado')
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('opened_at')
                            ->label('Apertura')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('closed_at')
                            ->label('Cierre')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Pagos incluidos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Fecha')
                                    ->date('d/m/Y'),

                                Infolists\Components\TextEntry::make('billing.family.name')
                                    ->label('Familia'),

                                Infolists\Components\TextEntry::make('billing.lines.service.name')
                                    ->label('Servicios')
                                    ->badge()
                                    ->separator(', '),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Método')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'cash'           => 'Efectivo',
                                        'bank_transfer'  => 'Transferencia',
                                        'mobile_payment' => 'Pago Móvil',
                                        default          => $state,
                                    }),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid'     => 'success',
                                        'reversed' => 'gray',
                                        default    => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'paid'     => 'Pagado',
                                        'reversed' => 'Anulado',
                                        default    => $state,
                                    }),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListJornadas::route('/'),
            'create' => Pages\CreateJornada::route('/create'),
            'view'   => Pages\ViewJornada::route('/{record}'),
        ];
    }
}
