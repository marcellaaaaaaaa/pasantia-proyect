<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    // ─── Query base ────────────────────────────────────────────────────────────

    /**
     * Admin solo ve usuarios de su propio tenant.
     * Super_admin ve todos.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                ! auth()->user()?->isSuperAdmin(),
                fn (Builder $q) => $q->where('tenant_id', auth()->user()->tenant_id)
            );
    }

    // ─── Form ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos personales')
                    ->schema([
                        // Comunidad: solo super_admin la elige
                        Forms\Components\Select::make('tenant_id')
                            ->label('Comunidad')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn () => auth()->user()?->isSuperAdmin()),

                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options(function () {
                                if (auth()->user()?->isSuperAdmin()) {
                                    return [
                                        User::ROLE_SUPER_ADMIN => 'Super Administrador',
                                        User::ROLE_ADMIN       => 'Administrador',
                                        User::ROLE_COLLECTOR   => 'Cobrador',
                                    ];
                                }

                                // Admin solo puede crear cobradores
                                return [User::ROLE_COLLECTOR => 'Cobrador'];
                            })
                            ->default(User::ROLE_COLLECTOR)
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contraseña')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            // Solo envía el valor si el campo no está vacío
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                            ->maxLength(255)
                            ->helperText(
                                fn (string $operation) => $operation === 'edit'
                                    ? 'Deja en blanco para no cambiar la contraseña actual.'
                                    : null
                            ),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(false)
                            ->same('password'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Asignación de calles')
                    ->description('Las calles asignadas determinan qué familias puede cobrar este usuario.')
                    ->schema([
                        Forms\Components\Select::make('sectors')
                            ->label('Calles asignadas')
                            ->multiple()
                            ->relationship(
                                name: 'sectors',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    $tenantId = auth()->user()?->isSuperAdmin()
                                        ? null
                                        : auth()->user()?->tenant_id;

                                    return $query->withoutGlobalScopes()
                                        ->when(
                                            $tenantId,
                                            fn (Builder $q) => $q->where('tenant_id', $tenantId)
                                        );
                                }
                            )
                            ->preload()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get) => $get('role') === User::ROLE_COLLECTOR)
                    ->collapsed(fn (string $operation) => $operation === 'create'),
            ]);
    }

    // ─── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rol')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        User::ROLE_SUPER_ADMIN => 'Super Admin',
                        User::ROLE_ADMIN       => 'Administrador',
                        User::ROLE_COLLECTOR   => 'Cobrador',
                        default                => $state,
                    })
                    ->colors([
                        'danger'  => User::ROLE_SUPER_ADMIN,
                        'warning' => User::ROLE_ADMIN,
                        'success' => User::ROLE_COLLECTOR,
                    ]),

                Tables\Columns\TextColumn::make('sectors_count')
                    ->label('Calles')
                    ->counts('sectors')
                    ->sortable()
                    ->placeholder('—'),

                // Solo super_admin ve la comunidad
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->placeholder('— (global)')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        User::ROLE_SUPER_ADMIN => 'Super Admin',
                        User::ROLE_ADMIN       => 'Administrador',
                        User::ROLE_COLLECTOR   => 'Cobrador',
                    ]),

                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record) => $record->id === auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    // ─── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
