<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Censo y Comunidad';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Inmueble';
    protected static ?string $pluralLabel = 'Inmuebles';

    public static function form(Form $form): Form
    {
        $tenantIdForFilter = fn (Forms\Get $get): ?int =>
            ($get('tenant_id') ? (int) $get('tenant_id') : auth()->user()?->tenant_id);

        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->label('Comunidad')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('sector_id', null))
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
            Forms\Components\Select::make('sector_id')
                ->label('Calle')
                ->relationship(
                    'sector',
                    'name',
                    fn ($query, Forms\Get $get) => $tenantIdForFilter($get)
                        ? $query->where('tenant_id', $tenantIdForFilter($get))
                        : $query
                )
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('address')->label('Dirección')->required(),
            Forms\Components\Select::make('type')->label('Tipo')->options(['house' => 'Casa', 'apartment' => 'Apartamento', 'commercial' => 'Comercial'])->required(),
            Forms\Components\TextInput::make('unit_number')->label('Número de Unidad (Apto #)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('address')->label('Dirección')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('sector.name')->label('Calle')->sortable(),
            Tables\Columns\TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state): string => match ($state) { 'house' => 'Casa', 'apartment' => 'Apartamento', 'commercial' => 'Comercial' }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProperties::route('/'), 'create' => Pages\CreateProperty::route('/create'), 'edit' => Pages\EditProperty::route('/{record}/edit')];
    }
}
