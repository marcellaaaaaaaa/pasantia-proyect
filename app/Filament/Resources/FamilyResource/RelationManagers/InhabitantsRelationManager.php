<?php

namespace App\Filament\Resources\FamilyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class InhabitantsRelationManager extends RelationManager
{
    protected static string $relationship = 'inhabitants';

    protected static ?string $title = 'Habitantes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

                        return Carbon::parse($dob)->age.' años';
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->placeholder('—'),

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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar habitante')
                    // Inyecta tenant_id al crear via RelationManager
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
