<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InhabitantResource\Pages;
use App\Models\Family;
use App\Models\Inhabitant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InhabitantResource extends Resource
{
    protected static ?string $model = Inhabitant::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Territorial';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Habitante';

    protected static ?string $pluralModelLabel = 'Habitantes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tenant_id')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Forms\Components\Select::make('family_id')
                    ->label('Familia')
                    ->required()
                    ->searchable()
                    ->options(function (Get $get) {
                        $tenantId = auth()->user()->isSuperAdmin()
                            ? $get('tenant_id')
                            : auth()->user()->tenant_id;

                        if (! $tenantId) {
                            return [];
                        }

                        return Family::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->with('property.sector')
                            ->get()
                            ->mapWithKeys(fn (Family $f) => [
                                $f->id => "{$f->name} — [{$f->property?->sector?->name}] {$f->property?->address}",
                            ]);
                    })
                    ->helperText(
                        fn () => auth()->user()->isSuperAdmin()
                            ? 'Selecciona primero la comunidad.'
                            : null
                    ),

                Forms\Components\TextInput::make('full_name')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(200),

                Forms\Components\TextInput::make('cedula')
                    ->label('Cédula')
                    ->maxLength(20)
                    ->nullable(),

                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Fecha de nacimiento')
                    ->nullable()
                    ->maxDate(now())
                    ->live(),

                Forms\Components\Placeholder::make('age')
                    ->label('Edad')
                    ->content(function (Get $get) {
                        $dob = $get('date_of_birth');

                        if (! $dob) {
                            return '—';
                        }

                        return \Illuminate\Support\Carbon::parse($dob)->age.' años';
                    })
                    ->visible(fn (Get $get) => filled($get('date_of_birth'))),

                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(30)
                    ->nullable(),

                Forms\Components\TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->maxLength(150)
                    ->nullable(),

                Forms\Components\Toggle::make('is_primary_contact')
                    ->label('Contacto principal')
                    ->helperText('Este habitante será el punto de contacto de la familia.')
                    ->default(false)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cédula')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->placeholder('—')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('date_of_birth', $direction === 'asc' ? 'desc' : 'asc')),

                Tables\Columns\TextColumn::make('family.name')
                    ->label('Familia')
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_primary_contact')
                    ->label('Contacto principal')
                    ->boolean(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
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
                    ->relationship('family.property.sector', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('property')
                    ->label('Inmueble')
                    ->relationship('family.property', 'address')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('family')
                    ->label('Familia')
                    ->relationship('family', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_primary_contact')
                    ->label('Contacto principal')
                    ->trueLabel('Sí')
                    ->falseLabel('No'),

                Tables\Filters\Filter::make('cedula')
                    ->form([
                        Forms\Components\TextInput::make('cedula')
                            ->label('Buscar por cédula')
                            ->placeholder('Cédula…'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['cedula'],
                        fn (Builder $q, string $value) => $q->where('cedula', 'like', "%{$value}%"),
                    )),

                Tables\Filters\Filter::make('phone')
                    ->form([
                        Forms\Components\TextInput::make('phone')
                            ->label('Buscar por teléfono')
                            ->placeholder('Teléfono…'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['phone'],
                        fn (Builder $q, string $value) => $q->where('phone', 'like', "%{$value}%"),
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
            ->defaultSort('full_name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInhabitants::route('/'),
            'create' => Pages\CreateInhabitant::route('/create'),
            'edit'   => Pages\EditInhabitant::route('/{record}/edit'),
        ];
    }
}
