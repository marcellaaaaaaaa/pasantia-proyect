<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Models\Person;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Censo y Comunidad';
    protected static ?int $navigationSort = 5;
    protected static ?string $label = 'Persona';
    protected static ?string $pluralLabel = 'Personas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->label('Comunidad')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
            Forms\Components\Select::make('family_id')->label('Familia')->relationship('family', 'name')->required(),
            Forms\Components\TextInput::make('full_name')->label('Nombre Completo')->required(),
            Forms\Components\TextInput::make('id_number')->label('Cédula / ID'),
            Forms\Components\DatePicker::make('birth_date')->label('Fecha de Nacimiento'),
            Forms\Components\TextInput::make('phone')->label('Teléfono'),
            Forms\Components\TextInput::make('email')->label('Email')->email(),
            Forms\Components\Toggle::make('is_primary_contact')->label('Contacto Principal'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('full_name')->label('Nombre')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('id_number')->label('Cédula'),
            Tables\Columns\TextColumn::make('family.name')->label('Familia')->sortable(),
            Tables\Columns\IconColumn::make('is_primary_contact')->label('Principal')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPeople::route('/'), 'create' => Pages\CreatePerson::route('/create'), 'edit' => Pages\EditPerson::route('/{record}/edit')];
    }
}
