<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers\FamiliesRelationManager;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Gestión de Cobros';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Servicio';
    protected static ?string $pluralLabel = 'Servicios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del Servicio')->schema([
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Servicio')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'fixed'   => 'Fijo (mensual automático)',
                        'jornada' => 'Jornada (puntual / eventual)',
                    ])
                    ->default('fixed')
                    ->required()
                    ->live()
                    ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                        'fixed'   => 'Se asigna a familias. Se genera factura automáticamente cada mes.',
                        'jornada' => 'Se cobra a través de una jornada. Ej: CLAP, gas doméstico.',
                        default   => '',
                    }),

                Forms\Components\TextInput::make('default_price_usd')
                    ->label('Precio Base ($)')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servicio')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed'   => 'success',
                        'jornada' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fixed'   => 'Fijo',
                        'jornada' => 'Jornada',
                        default   => $state,
                    }),

                Tables\Columns\TextColumn::make('default_price_usd')
                    ->label('Precio ($)')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('families_count')
                    ->label('Familias')
                    ->counts('families')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(['fixed' => 'Fijo', 'jornada' => 'Jornada']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('type');
    }

    public static function getRelationManagers(): array
    {
        return [
            FamiliesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit'   => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
