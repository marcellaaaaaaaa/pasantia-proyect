<?php

namespace App\Filament\Resources;

use App\Exceptions\InsufficientBalanceException;
use App\Filament\Resources\RemittanceResource\Pages;
use App\Models\Remittance;
use App\Services\RemittanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use InvalidArgumentException;
use LogicException;

class RemittanceResource extends Resource
{
    protected static ?string $model = Remittance::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Cobros';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Liquidación';

    protected static ?string $pluralModelLabel = 'Liquidaciones';

    /** Muestra un badge con el número de remesas esperando revisión */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::pendingReview()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // Sin formulario de creación manual: las remesas se crean desde la PWA
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_declared')
                    ->label('Declarado')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_confirmed')
                    ->label('Confirmado')
                    ->money('USD')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'submitted',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'     => 'Borrador',
                        'submitted' => 'Enviada',
                        'approved'  => 'Aprobada',
                        'rejected'  => 'Rechazada',
                        default     => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Enviada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Revisada por')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Revisada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            // Resaltar filas con status submitted para que no pasen desapercibidas
            ->recordClasses(fn (Remittance $record): string =>
                $record->isSubmitted() ? 'bg-warning-50 dark:bg-warning-900/20' : ''
            )
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccione')
                    ->options([
                        'draft'     => 'Borrador',
                        'submitted' => 'Enviada',
                        'approved'  => 'Aprobada',
                        'rejected'  => 'Rechazada',
                    ]),

                Tables\Filters\SelectFilter::make('collector')
                    ->label('Cobrador')
                    ->relationship('collector', 'name')
                    ->placeholder('Seleccione')
                    ->searchable()
                    ->preload(),

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
                // ── Aprobar (solo para admin, solo en estado submitted) ──────────
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Remittance $record): bool =>
                        $record->isSubmitted() && ! auth()->user()?->isSuperAdmin()
                    )
                    ->form(fn (Remittance $record): array => [
                        Forms\Components\TextInput::make('amount_confirmed')
                            ->label('Monto confirmado físicamente')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->default(fn () => (float) $record->amount_declared)
                            ->prefix('$')
                            ->step('0.01')
                            ->helperText(
                                "Declarado por el cobrador: \${$record->amount_declared}. "
                                . 'Se permite hasta un 5% de diferencia.'
                            ),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notas (opcional)')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->modalHeading(fn (Remittance $record): string =>
                        "Aprobar liquidación #{$record->id} — {$record->collector?->name}"
                    )
                    ->modalSubmitActionLabel('Confirmar aprobación')
                    ->action(function (
                        Remittance              $record,
                        array                   $data,
                        Tables\Actions\Action   $action,
                    ): void {
                        try {
                            app(RemittanceService::class)->approve(
                                remittance:      $record,
                                admin:           auth()->user(),
                                amountConfirmed: (float) $data['amount_confirmed'],
                                notes:           $data['admin_notes'] ?? null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Liquidación aprobada')
                                ->body("Se acreditaron \${$data['amount_confirmed']} al vault de la comunidad.")
                                ->send();
                        } catch (InvalidArgumentException | InsufficientBalanceException | LogicException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo aprobar la liquidación')
                                ->body($e->getMessage())
                                ->send();

                            $action->halt();
                        }
                    }),

                // ── Rechazar (solo para admin, solo en estado submitted) ──────────
                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Remittance $record): bool =>
                        $record->isSubmitted() && ! auth()->user()?->isSuperAdmin()
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Motivo del rechazo')
                            ->required()
                            ->rows(3)
                            ->minLength(10)
                            ->helperText('Explica al cobrador por qué se rechaza la liquidación.'),
                    ])
                    ->modalHeading(fn (Remittance $record): string =>
                        "Rechazar liquidación #{$record->id} — {$record->collector?->name}"
                    )
                    ->modalSubmitActionLabel('Confirmar rechazo')
                    ->modalSubmitAction(fn (Tables\Actions\Action $action) =>
                        $action->color('danger')
                    )
                    ->action(function (
                        Remittance              $record,
                        array                   $data,
                        Tables\Actions\Action   $action,
                    ): void {
                        try {
                            app(RemittanceService::class)->reject(
                                remittance: $record,
                                admin:      auth()->user(),
                                notes:      $data['admin_notes'],
                            );

                            Notification::make()
                                ->warning()
                                ->title('Liquidación rechazada')
                                ->body('Los pagos incluidos quedaron disponibles para una nueva liquidación.')
                                ->send();
                        } catch (LogicException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo rechazar la liquidación')
                                ->body($e->getMessage())
                                ->send();

                            $action->halt();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRemittances::route('/'),
            'view'  => Pages\ViewRemittance::route('/{record}'),
        ];
    }
}
