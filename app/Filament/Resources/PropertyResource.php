<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Models\Property;
use App\Models\Sector;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Territorial';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Inmueble';

    protected static ?string $pluralModelLabel = 'Inmuebles';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Comunidad: solo super_admin la elige; admin usa la suya automáticamente
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                // Sector: se filtra según el tenant seleccionado (o el del admin)
                Forms\Components\Select::make('sector_id')
                    ->label('Calle / Sector')
                    ->required()
                    ->searchable()
                    ->options(function (Get $get) {
                        $tenantId = auth()->user()->isSuperAdmin()
                            ? $get('tenant_id')
                            : auth()->user()->tenant_id;

                        if (! $tenantId) {
                            return [];
                        }

                        return Sector::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->pluck('name', 'id');
                    })
                    ->helperText(
                        fn () => auth()->user()->isSuperAdmin()
                            ? 'Selecciona primero la comunidad para ver sus calles.'
                            : null
                    ),

                Forms\Components\Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'house'      => 'Casa',
                        'apartment'  => 'Apartamento',
                        'commercial' => 'Local comercial',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('address')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('unit_number')
                    ->label('Número de unidad')
                    ->maxLength(20)
                    ->nullable()
                    ->visible(fn (Get $get) => $get('type') === 'apartment')
                    ->helperText('Ej: Apto 4B, Piso 3'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sector.name')
                    ->label('Calle / Sector')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'house'      => 'Casa',
                        'apartment'  => 'Apartamento',
                        'commercial' => 'Local comercial',
                        default      => $state,
                    })
                    ->colors([
                        'info'    => 'house',
                        'warning' => 'apartment',
                        'gray'    => 'commercial',
                    ]),

                Tables\Columns\TextColumn::make('unit_number')
                    ->label('Unidad')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('families_count')
                    ->label('Familias')
                    ->counts('families')
                    ->sortable(),

                // Solo super_admin ve la columna de comunidad
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
                Tables\Filters\SelectFilter::make('sector')
                    ->label('Calle / Sector')
                    ->relationship('sector', 'name'),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'house'      => 'Casa',
                        'apartment'  => 'Apartamento',
                        'commercial' => 'Local comercial',
                    ]),

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
            ->defaultSort('address');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit'   => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
