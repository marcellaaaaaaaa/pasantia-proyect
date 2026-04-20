<?php

namespace App\Filament\Resources\FamilyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';
    protected static ?string $title = 'Servicios Fijos Asignados';
    protected static ?string $modelLabel = 'Servicio';
    protected static ?string $pluralModelLabel = 'Servicios';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servicio')
                    ->searchable(),

                Tables\Columns\TextColumn::make('default_price_usd')
                    ->label('Precio Base ($)')
                    ->money('USD'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Asignar Servicio Fijo')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query->where('is_active', true)->where('type', 'fixed')
                    ),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Quitar'),
            ])
            ->emptyStateHeading('Sin servicios asignados')
            ->emptyStateDescription('Asigna servicios fijos para que se generen facturas automáticas cada mes.');
    }
}
