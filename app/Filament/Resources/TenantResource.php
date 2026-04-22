<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Censo y Comunidad';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Comunidad';
    protected static ?string $pluralLabel = 'Comunidades';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            Forms\Components\Select::make('status')->label('Estado')->options(['active' => 'Activa', 'inactive' => 'Inactiva'])->default('active')->required(),
            Forms\Components\Textarea::make('notes')->label('Comentario')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('notes')->label('Comentario')->limit(50)->toggleable(),
            Tables\Columns\TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) { 'active' => 'success', 'inactive' => 'danger' })->formatStateUsing(fn (string $state): string => match ($state) { 'active' => 'Activa', 'inactive' => 'Inactiva' }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTenants::route('/'), 'create' => Pages\CreateTenant::route('/create'), 'edit' => Pages\EditTenant::route('/{record}/edit')];
    }
}
