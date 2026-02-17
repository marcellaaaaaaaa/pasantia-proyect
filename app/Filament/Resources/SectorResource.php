<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SectorResource\Pages;
use App\Models\Sector;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SectorResource extends Resource
{
    protected static ?string $model = Sector::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Territorial';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Calle / Sector';

    protected static ?string $pluralModelLabel = 'Calles / Sectores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Solo super_admin elige el tenant; admin siempre crea en el suyo
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(150),

                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                // Columna de comunidad visible solo para super_admin
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('properties_count')
                    ->label('Inmuebles')
                    ->counts('properties')
                    ->sortable(),

                Tables\Columns\TextColumn::make('collectors_count')
                    ->label('Cobradores')
                    ->counts('collectors')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSectors::route('/'),
            'create' => Pages\CreateSector::route('/create'),
            'edit'   => Pages\EditSector::route('/{record}/edit'),
        ];
    }
}
