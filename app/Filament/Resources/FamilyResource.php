<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FamilyResource\Pages;
use App\Filament\Resources\FamilyResource\RelationManagers\InhabitantsRelationManager;
use App\Models\Family;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FamilyResource extends Resource
{
    protected static ?string $model = Family::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Territorial';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Familia';

    protected static ?string $pluralModelLabel = 'Familias';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Comunidad: solo super_admin la elige
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                // Inmueble: filtrado por tenant
                Forms\Components\Select::make('property_id')
                    ->label('Inmueble')
                    ->required()
                    ->searchable()
                    ->options(function (Get $get) {
                        $tenantId = auth()->user()->isSuperAdmin()
                            ? $get('tenant_id')
                            : auth()->user()->tenant_id;

                        if (! $tenantId) {
                            return [];
                        }

                        return Property::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->with('sector')
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => "[{$p->sector->name}] {$p->address}"
                                    .($p->unit_number ? " – {$p->unit_number}" : ''),
                            ]);
                    })
                    ->helperText(
                        fn () => auth()->user()->isSuperAdmin()
                            ? 'Selecciona primero la comunidad.'
                            : null
                    ),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre de la familia')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ej: Familia González'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Familia')
                    ->sortable(),

                Tables\Columns\TextColumn::make('property.address')
                    ->label('Inmueble')
                    ->description(fn (Family $record) => $record->property?->sector?->name)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('inhabitants_count')
                    ->label('Habitantes')
                    ->counts('inhabitants')
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
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Filters\SelectFilter::make('sector')
                    ->label('Calle / Sector')
                    ->relationship('property.sector', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('property')
                    ->label('Inmueble')
                    ->relationship('property', 'address')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo activas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),

                Tables\Filters\Filter::make('habitante')
                    ->form([
                        Forms\Components\TextInput::make('habitante')
                            ->label('Buscar por habitante')
                            ->placeholder('Nombre o cédula…'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['habitante'],
                        fn (Builder $q, string $value) => $q->whereHas('inhabitants', fn (Builder $sub) => $sub
                            ->where('full_name', 'like', "%{$value}%")
                            ->orWhere('cedula', 'like', "%{$value}%")
                        ),
                    )),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
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

    public static function getRelations(): array
    {
        return [
            InhabitantsRelationManager::class,
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
