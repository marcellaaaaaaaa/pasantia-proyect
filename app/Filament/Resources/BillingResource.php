<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingResource\Pages;
use App\Models\Billing;
use App\Models\Family;
use App\Models\Service;
use App\Services\BillingGenerationService;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

class BillingResource extends Resource
{
    protected static ?string $model = Billing::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Cobros';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Cobro';

    protected static ?string $pluralModelLabel = 'Cobros';

    // Solo lectura + acción de pago: sin formulario de creación manual
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('family.name')
                    ->label('Familia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('family.property.address')
                    ->label('Inmueble')
                    ->description(fn (Billing $r) => $r->family?->property?->sector?->name)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Servicio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period')
                    ->label('Período')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto total')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pending',
                        'info'    => 'partial',
                        'success' => 'paid',
                        'gray'    => 'cancelled',
                        'danger'  => 'void',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Pendiente',
                        'partial'   => 'Parcial',
                        'paid'      => 'Pagado',
                        'cancelled' => 'Cancelado',
                        'void'      => 'Anulado',
                        default     => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Billing $r): string =>
                        $r->due_date?->isPast() && $r->status !== 'paid' ? 'danger' : 'default'
                    ),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Comunidad')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'   => 'Pendiente',
                        'partial'   => 'Parcial',
                        'paid'      => 'Pagado',
                        'cancelled' => 'Cancelado',
                        'void'      => 'Anulado',
                    ]),

                Tables\Filters\SelectFilter::make('service')
                    ->label('Servicio')
                    ->relationship('service', 'name'),

                Tables\Filters\Filter::make('period')
                    ->form([
                        Forms\Components\TextInput::make('period')
                            ->label('Período (YYYY-MM)')
                            ->placeholder(now()->format('Y-m'))
                            ->regex('/^\d{4}-\d{2}$/'),
                    ])
                    ->query(fn ($query, array $data) =>
                        $query->when($data['period'], fn ($q, $v) => $q->byPeriod($v))
                    ),

                Tables\Filters\Filter::make('vencidos')
                    ->label('Vencidos sin pagar')
                    ->query(fn ($query) =>
                        $query->whereIn('status', ['pending', 'partial'])
                              ->where('due_date', '<', now()->toDateString())
                    ),

                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->headerActions([
                // Acción de encabezado: genera cobros de un período completo
                Tables\Actions\Action::make('generar_cobros')
                    ->label('Generar Cobros')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->visible(fn () => ! auth()->user()?->isSuperAdmin()) // solo admin de tenant
                    ->form([
                        Forms\Components\TextInput::make('period')
                            ->label('Período (YYYY-MM)')
                            ->required()
                            ->default(now()->format('Y-m'))
                            ->regex('/^\d{4}-\d{2}$/')
                            ->helperText('Formato: 2026-02'),
                    ])
                    ->action(function (array $data): void {
                        $tenant = auth()->user()->tenant;
                        $result = app(BillingGenerationService::class)
                            ->generateForTenant($tenant, $data['period']);

                        Notification::make()
                            ->success()
                            ->title("Cobros generados: {$result['created']} nuevos, {$result['skipped']} ya existían")
                            ->send();
                    }),
            ])
            ->actions([
                // Acción principal: registrar un pago sobre el cobro
                Tables\Actions\Action::make('registrar_pago')
                    ->label('Registrar Pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Billing $record): bool =>
                        ! in_array($record->status, ['paid', 'cancelled', 'void'])
                    )
                    ->form(fn (Billing $record): array => [
                        Forms\Components\TextInput::make('amount')
                            ->label('Monto recibido')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->default(fn () => $record->amount_pending)
                            ->prefix('$')
                            ->helperText("Pendiente: \${$record->amount_pending}"),

                        Forms\Components\Select::make('payment_method')
                            ->label('Método de pago')
                            ->required()
                            ->options([
                                'cash'           => 'Efectivo',
                                'bank_transfer'  => 'Transferencia bancaria',
                                'mobile_payment' => 'Pago móvil',
                            ]),

                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia / N° de operación')
                            ->nullable()
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get): bool =>
                                in_array($get('payment_method'), ['bank_transfer', 'mobile_payment'])
                            ),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->modalHeading(fn (Billing $record): string =>
                        "Registrar pago — {$record->family?->name} / {$record->service?->name} ({$record->period})"
                    )
                    ->action(function (Billing $record, array $data, Tables\Actions\Action $action): void {
                        try {
                            app(PaymentService::class)->register(
                                billing:       $record,
                                collector:     auth()->user(),
                                amount:        (float) $data['amount'],
                                paymentMethod: $data['payment_method'],
                                reference:     $data['reference'] ?? null,
                                notes:         $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Pago registrado correctamente')
                                ->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al registrar el pago')
                                ->body($e->getMessage())
                                ->send();

                            $action->halt();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])  // Sin bulk actions en un recurso financiero
            ->defaultSort('due_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillings::route('/'),
            'view'  => Pages\ViewBilling::route('/{record}'),
        ];
    }
}
