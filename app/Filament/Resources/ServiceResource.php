<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Cobros';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Servicio';

    protected static ?string $pluralModelLabel = 'Servicios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Comunidad: solo visible para super_admin
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre del servicio')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Ej: Aseo Urbano'),

                Forms\Components\TextInput::make('default_price')
                    ->label('Precio mensual')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->step('0.01'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Los servicios inactivos no se incluyen en la generación de cobros.'),

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
                    ->sortable(),

                Tables\Columns\TextColumn::make('default_price')
                    ->label('Precio mensual')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('billings_count')
                    ->label('Cobros')
                    ->counts('billings')
                    ->sortable(),

                // Solo super_admin ve la comunidad
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('name')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Buscar por nombre')
                            ->placeholder('Nombre del servicio…'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['name'],
                        fn (Builder $q, string $value) => $q->where('name', 'like', "%{$value}%"),
                    )),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Seleccione')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),

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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('name');
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
