<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Opciones';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Usuario';
    protected static ?string $pluralLabel = 'Usuarios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->label('Correo Electrónico')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Forms\Components\Select::make('role')
                ->label('Rol')
                ->options([
                    'super_admin' => 'Súper Administrador',
                    'admin' => 'Administrador de Comunidad',
                    'collector' => 'Cobrador',
                ])
                ->required(),
            Forms\Components\Select::make('tenant_id')
                ->label('Comunidad')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context): bool => $context === 'create')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
            Tables\Columns\TextColumn::make('role')
                ->label('Rol')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'super_admin' => 'Súper Admin',
                    'admin' => 'Administrador',
                    'collector' => 'Cobrador',
                    default => $state,
                }),
            Tables\Columns\TextColumn::make('tenant.name')
                ->label('Comunidad')
                ->sortable()
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\ViewAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
