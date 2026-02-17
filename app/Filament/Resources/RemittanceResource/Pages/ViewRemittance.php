<?php

namespace App\Filament\Resources\RemittanceResource\Pages;

use App\Exceptions\InsufficientBalanceException;
use App\Filament\Resources\RemittanceResource;
use App\Models\Remittance;
use App\Services\RemittanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;
use LogicException;

class ViewRemittance extends ViewRecord
{
    protected static string $resource = RemittanceResource::class;

    /**
     * Header actions: Aprobar y Rechazar disponibles directamente en la vista de detalle.
     */
    protected function getHeaderActions(): array
    {
        /** @var Remittance $remittance */
        $remittance = $this->getRecord();

        return [
            // ── Aprobar ─────────────────────────────────────────────────────────
            Actions\Action::make('aprobar')
                ->label('Aprobar liquidación')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool =>
                    $remittance->isSubmitted() && ! auth()->user()?->isSuperAdmin()
                )
                ->form([
                    Forms\Components\TextInput::make('amount_confirmed')
                        ->label('Monto confirmado físicamente')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->default(fn () => (float) $remittance->amount_declared)
                        ->prefix('$')
                        ->step('0.01')
                        ->helperText(
                            "Declarado: \${$remittance->amount_declared}. "
                            . 'Tolerancia máxima: 5%.'
                        ),

                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Notas (opcional)')
                        ->rows(2)
                        ->nullable(),
                ])
                ->modalSubmitActionLabel('Confirmar aprobación')
                ->action(function (array $data, Actions\Action $action): void {
                    /** @var Remittance $remittance */
                    $remittance = $this->getRecord();

                    try {
                        app(RemittanceService::class)->approve(
                            remittance:      $remittance,
                            admin:           auth()->user(),
                            amountConfirmed: (float) $data['amount_confirmed'],
                            notes:           $data['admin_notes'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Liquidación aprobada')
                            ->body("Se acreditaron \${$data['amount_confirmed']} al vault de la comunidad.")
                            ->send();

                        // Refrescar el infolist para mostrar el nuevo estado
                        $this->refreshFormData(['status', 'amount_confirmed', 'reviewed_at']);
                    } catch (InvalidArgumentException | InsufficientBalanceException | LogicException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo aprobar')
                            ->body($e->getMessage())
                            ->send();

                        $action->halt();
                    }
                }),

            // ── Rechazar ────────────────────────────────────────────────────────
            Actions\Action::make('rechazar')
                ->label('Rechazar liquidación')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    $remittance->isSubmitted() && ! auth()->user()?->isSuperAdmin()
                )
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(3)
                        ->minLength(10)
                        ->helperText('El cobrador verá este mensaje para corregir la liquidación.'),
                ])
                ->modalSubmitActionLabel('Confirmar rechazo')
                ->modalSubmitAction(fn (Actions\Action $action) => $action->color('danger'))
                ->action(function (array $data, Actions\Action $action): void {
                    /** @var Remittance $remittance */
                    $remittance = $this->getRecord();

                    try {
                        app(RemittanceService::class)->reject(
                            remittance: $remittance,
                            admin:      auth()->user(),
                            notes:      $data['admin_notes'],
                        );

                        Notification::make()
                            ->warning()
                            ->title('Liquidación rechazada')
                            ->body('Los pagos quedaron liberados para una nueva liquidación.')
                            ->send();

                        $this->refreshFormData(['status', 'reviewed_at', 'admin_notes']);
                    } catch (LogicException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo rechazar')
                            ->body($e->getMessage())
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ── Encabezado ──────────────────────────────────────────────────
                Infolists\Components\Section::make('Datos de la liquidación')
                    ->schema([
                        Infolists\Components\TextEntry::make('collector.name')
                            ->label('Cobrador'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft'     => 'gray',
                                'submitted' => 'warning',
                                'approved'  => 'success',
                                'rejected'  => 'danger',
                                default     => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'draft'     => 'Borrador',
                                'submitted' => 'Enviada — esperando revisión',
                                'approved'  => 'Aprobada',
                                'rejected'  => 'Rechazada',
                                default     => $state,
                            }),

                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('Fecha de envío')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('reviewer.name')
                            ->label('Revisada por')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('reviewed_at')
                            ->label('Fecha de revisión')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Comunidad')
                            ->visible(fn () => auth()->user()?->isSuperAdmin()),
                    ])
                    ->columns(3),

                // ── Montos ──────────────────────────────────────────────────────
                Infolists\Components\Section::make('Montos')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount_declared')
                            ->label('Declarado por el cobrador')
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('amount_confirmed')
                            ->label('Confirmado por el admin')
                            ->money('USD')
                            ->placeholder('—')
                            ->color(fn ($record): string =>
                                $record->amount_confirmed &&
                                bccomp(
                                    (string) $record->amount_confirmed,
                                    (string) $record->amount_declared,
                                    2
                                ) !== 0
                                    ? 'warning'
                                    : 'default'
                            ),

                        Infolists\Components\TextEntry::make('payments_count')
                            ->label('Pagos incluidos')
                            ->state(fn ($record) => $record->payments()->count()),
                    ])
                    ->columns(3),

                // ── Notas ───────────────────────────────────────────────────────
                Infolists\Components\Section::make('Notas')
                    ->schema([
                        Infolists\Components\TextEntry::make('collector_notes')
                            ->label('Notas del cobrador')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Notas del admin')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(fn ($record) =>
                        ! $record->collector_notes && ! $record->admin_notes
                    ),

                // ── Pagos incluidos ─────────────────────────────────────────────
                Infolists\Components\Section::make('Pagos incluidos en esta liquidación')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Fecha')
                                    ->date('d/m/Y'),

                                Infolists\Components\TextEntry::make('billing.family.name')
                                    ->label('Familia'),

                                Infolists\Components\TextEntry::make('billing.service.name')
                                    ->label('Servicio'),

                                Infolists\Components\TextEntry::make('billing.period')
                                    ->label('Período'),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Método')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'cash'           => 'Efectivo',
                                        'bank_transfer'  => 'Transferencia',
                                        'mobile_payment' => 'Pago móvil',
                                        default          => $state,
                                    }),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending_remittance' => 'warning',
                                        'conciliated'        => 'success',
                                        'reversed'           => 'danger',
                                        default              => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending_remittance' => 'Pendiente remesa',
                                        'conciliated'        => 'Conciliado',
                                        'reversed'           => 'Anulado',
                                        default              => $state,
                                    }),

                                Infolists\Components\TextEntry::make('reference')
                                    ->label('Referencia')
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
