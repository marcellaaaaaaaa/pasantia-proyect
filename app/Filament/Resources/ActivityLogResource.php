<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Opciones';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Log de Actividad';
    protected static ?string $pluralLabel = 'Logs de Actividad';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('description')->label('Descripción'),
            Forms\Components\TextInput::make('subject_type')->label('Tipo de Sujeto'),
            Forms\Components\TextInput::make('causer_type')->label('Tipo de Causante'),
            Forms\Components\DateTimePicker::make('created_at')->label('Fecha'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('description')->label('Descripción')->searchable(),
            Tables\Columns\TextColumn::make('subject_type')->label('Modelo'),
            Tables\Columns\TextColumn::make('causer.name')->label('Usuario'),
            Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\ViewAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
