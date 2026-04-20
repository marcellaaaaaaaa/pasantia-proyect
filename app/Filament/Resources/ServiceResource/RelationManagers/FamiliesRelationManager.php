<?php

namespace App\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FamiliesRelationManager extends RelationManager
{
    protected static string $relationship = 'families';
    protected static ?string $title = 'Familias Suscritas';
    protected static ?string $modelLabel = 'Familia';
    protected static ?string $pluralModelLabel = 'Familias';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Familia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('property.address')
                    ->label('Dirección'),

                Tables\Columns\TextColumn::make('property.sector.name')
                    ->label('Sector'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Agregar Familia')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query->where('is_active', true)->with('property.sector')
                    )
                    ->recordSelectSearchColumns(['name']),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('Quitar'),
            ])
            ->emptyStateHeading('Sin familias suscritas')
            ->emptyStateDescription('Agrega las familias que recibirán este servicio en su factura mensual.');
    }
}
