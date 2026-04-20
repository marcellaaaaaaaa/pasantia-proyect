<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FamilyResource\Pages;
use App\Filament\Resources\FamilyResource\RelationManagers\ServicesRelationManager;
use App\Models\Family;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FamilyResource extends Resource
{
    protected static ?string $model = Family::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Censo y Comunidad';
    protected static ?int $navigationSort = 4;
    protected static ?string $label = 'Familia';
    protected static ?string $pluralLabel = 'Familias';

    public static function form(Form $form): Form
    {
        $isAdmin = auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin();

        return $form->schema([
            Forms\Components\Section::make('Datos del Hogar')->schema([
                Forms\Components\Select::make('property_id')
                    ->label('Inmueble')
                    ->relationship('property', 'address')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre de la Familia')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Exoneración')
                ->description('Solo el administrador puede autorizar exoneraciones.')
                ->schema([
                    Forms\Components\Toggle::make('is_exonerated')
                        ->label('Familia Exonerada')
                        ->helperText('Una familia exonerada se considera solvente independientemente de sus deudas.')
                        ->reactive(),

                    Forms\Components\Textarea::make('exoneration_reason')
                        ->label('Motivo de Exoneración')
                        ->visible(fn (Forms\Get $get) => $get('is_exonerated'))
                        ->required(fn (Forms\Get $get) => $get('is_exonerated'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->visible($isAdmin)
                ->collapsible()
                ->collapsed(fn ($record) => ! $record?->is_exonerated),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Familia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('property.address')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('property.sector.name')
                    ->label('Sector')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_exonerated')
                    ->label('Exonerada')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->toggleable(),

                // Solvencia calculada en tiempo real
                Tables\Columns\IconColumn::make('solvency')
                    ->label('Solvente')
                    ->state(fn (Family $record): bool => $record->isSolvent())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options(['1' => 'Activas', '0' => 'Inactivas'])
                    ->default('1'),

                SelectFilter::make('property.sector_id')
                    ->label('Sector')
                    ->relationship('property.sector', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelationManagers(): array
    {
        return [
            ServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFamilies::route('/'),
            'create' => Pages\CreateFamily::route('/create'),
            'edit'   => Pages\EditFamily::route('/{record}/edit'),
        ];
    }
}
