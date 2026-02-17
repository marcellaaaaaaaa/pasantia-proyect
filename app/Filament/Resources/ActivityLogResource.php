<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * FIL-012 — ActivityLogResource
 *
 * Solo lectura para admin/super_admin.
 * Muestra el log de auditoría de eventos del sistema:
 *   - Registros de pago
 *   - Aprobación/rechazo de remesas
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Auditoría';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Evento')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'Pago')    => 'success',
                        str_contains($state, 'aprobada') => 'info',
                        str_contains($state, 'rechazada') => 'danger',
                        default                          => 'gray',
                    }),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->searchable()
                    ->placeholder('Sistema'),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (?string $state): string =>
                        $state ? class_basename($state) : '—'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Objeto')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Canal')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label('Tipo de evento')
                    ->options([
                        'Pago registrado'  => 'Pago registrado',
                        'Remesa aprobada'  => 'Remesa aprobada',
                        'Remesa rechazada' => 'Remesa rechazada',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),

                Tables\Filters\Filter::make('causer')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('causer_name')
                            ->label('Usuario (nombre)'),
                    ])
                    ->query(fn ($query, array $data) =>
                        $query->when($data['causer_name'], fn ($q, $v) =>
                            $q->whereHasMorph('causer', '*', fn ($cq) =>
                                $cq->where('name', 'like', "%{$v}%")
                            )
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Detalle del Evento')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Evento')
                            ->badge(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y H:i:s'),

                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Realizado por')
                            ->placeholder('Sistema'),

                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Tipo de objeto')
                            ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('ID del objeto'),

                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Canal de log'),
                    ]),

                Infolists\Components\Section::make('Propiedades')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('properties')
                            ->label('Datos del evento')
                            ->getStateUsing(fn ($record) => $record->properties?->toArray() ?? []),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
