<?php

namespace App\Filament\Resources;

use App\Application\Billing\Commands\RegisterCollectionCommand;
use App\Application\Billing\Handlers\RegisterCollectionHandler;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Gestión de Cobros';
    protected static ?int $navigationSort = 3;
    protected static ?string $label = 'Factura';
    protected static ?string $pluralLabel = 'Facturas';

    protected const STATUS_LABELS = [
        'pending'    => 'Pendiente',
        'approved'   => 'Aprobada',
        'partial'    => 'Parcial',
        'collected'  => 'Cobrada',
        'exonerated' => 'Exonerada',
        'cancelled'  => 'Cancelada',
    ];

    protected const STATUS_TRANSITIONS = [
        'pending'    => ['approved', 'cancelled'],
        'approved'   => ['cancelled', 'exonerated'],
        'partial'    => ['cancelled', 'exonerated'],
        'collected'  => [],
        'exonerated' => [],
        'cancelled'  => [],
    ];

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
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
                Forms\Components\Select::make('family_id')->label('Familia')->relationship('family', 'name')->required()->searchable()->preload(),
                Forms\Components\TextInput::make('description')->label('Descripción')->required(),
                Forms\Components\TextInput::make('amount_usd')->label('Monto ($)')->numeric()->prefix('$')->required(),
                Forms\Components\TextInput::make('collected_amount_usd')->label('Recaudado ($)')->numeric()->prefix('$')->disabled(),
                Forms\Components\Select::make('status')->label('Estado')->options(self::STATUS_LABELS)->required(),
                Forms\Components\DatePicker::make('due_date')->label('Vencimiento'),
            ])
            ->disabled(fn (?Invoice $record): bool => $record !== null && $record->status !== 'pending');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('family.name')->label('Familia')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->label('Descripción')->limit(30),
            Tables\Columns\TextColumn::make('amount_usd')->label('Monto ($)')->money('USD')->sortable(),
            Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->color(fn (string $state): string => self::statusColor($state))
                ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state),
        ])->actions([
            Tables\Actions\ActionGroup::make([
                Tables\Actions\EditAction::make()->label('Editar'),

                Tables\Actions\Action::make('changeStatus')
                    ->label('Cambiar estado')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Invoice $record): bool => ! empty(self::STATUS_TRANSITIONS[$record->status] ?? []))
                    ->modalHeading('Cambiar estado de la factura')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->form(fn (Invoice $record): array => self::changeStatusForm($record)),

                Tables\Actions\Action::make('createCollection')
                    ->label('Crear cobro')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => in_array($record->status, ['approved', 'partial']))
                    ->modalHeading(fn (Invoice $record): string => "Registrar cobro — Factura #{$record->id}")
                    ->modalSubmitActionLabel('Registrar cobro')
                    ->form(fn (Invoice $record): array => self::collectionForm($record))
                    ->action(function (array $data, Invoice $record): void {
                        try {
                            app(RegisterCollectionHandler::class)->handle(new RegisterCollectionCommand(
                                invoice_id:    $record->id,
                                amount:        (float) $data['amount'],
                                currency:      $data['currency'],
                                exchange_rate: (float) ($data['exchange_rate'] ?? 1),
                                method:        $data['method'],
                                reference:     $data['reference'] ?? null,
                                notes:         $data['notes'] ?? null,
                                collector_id:  auth()->id(),
                            ));

                            Notification::make()
                                ->title('Cobro registrado')
                                ->success()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('No se pudo registrar el cobro')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label('Opciones')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ]);
    }

    protected static function statusColor(string $status): string
    {
        return match ($status) {
            'pending'    => 'danger',
            'approved'   => 'info',
            'partial'    => 'warning',
            'collected'  => 'success',
            'exonerated' => 'success',
            'cancelled'  => 'gray',
            default      => 'gray',
        };
    }

    protected static function changeStatusForm(Invoice $record): array
    {
        $transitions = collect(self::STATUS_TRANSITIONS[$record->status] ?? [])
            ->sortBy(fn (string $status) => $status === 'cancelled' ? 1 : 0)
            ->values();

        $blocks = [
            Forms\Components\Placeholder::make('current_status')
                ->label('Estado actual')
                ->content(self::STATUS_LABELS[$record->status] ?? $record->status),
        ];

        foreach ($transitions as $status) {
            $blocks[] = Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make("to_{$status}")
                    ->label('Cambiar a "' . (self::STATUS_LABELS[$status] ?? $status) . '"')
                    ->color(self::statusColor($status))
                    ->size(\Filament\Support\Enums\ActionSize::Large)
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => 'Confirmar cambio a "' . (self::STATUS_LABELS[$status] ?? $status) . '"')
                    ->action(function ($livewire) use ($record, $status): void {
                        $record->update(['status' => $status]);
                        Notification::make()
                            ->title('Estado actualizado a "' . (self::STATUS_LABELS[$status] ?? $status) . '"')
                            ->success()
                            ->send();
                        $livewire->unmountTableAction();
                    }),
            ])->fullWidth();
        }

        return $blocks;
    }

    protected static function collectionForm(Invoice $invoice): array
    {
        return [
            Forms\Components\Placeholder::make('factura_info')
                ->label('Factura')
                ->content("#{$invoice->id} — {$invoice->family?->name} — {$invoice->description}"),

            Forms\Components\Placeholder::make('saldo_info')
                ->label('Saldo pendiente')
                ->content(fn (): string => '$' . number_format((float) $invoice->balance_usd, 2)),

            Forms\Components\Select::make('currency')
                ->label('Moneda')
                ->options(['VED' => 'Bolívares (VED)', 'USD' => 'Dólares (USD)'])
                ->default('VED')
                ->required()
                ->live(),

            Forms\Components\TextInput::make('exchange_rate')
                ->label('Tasa BCV')
                ->numeric()
                ->default(fn () => optional(ExchangeRate::forToday())->rate_usd)
                ->disabled()
                ->dehydrated()
                ->visible(fn (Forms\Get $get) => $get('currency') === 'VED')
                ->helperText(function () {
                    $rate = ExchangeRate::forToday();
                    return $rate
                        ? "Tasa del día ({$rate->date->format('d/m/Y')}): {$rate->rate_usd}"
                        : '⚠ No hay tasa cargada para hoy. Contacte al administrador.';
                }),

            Forms\Components\TextInput::make('amount')
                ->label('Monto Cobrado')
                ->numeric()
                ->minValue(0.01)
                ->required(),

            Forms\Components\Select::make('method')
                ->label('Método de Pago')
                ->options([
                    'cash'           => 'Efectivo',
                    'transfer'       => 'Transferencia',
                    'mobile_payment' => 'Pago Móvil',
                ])
                ->required(),

            Forms\Components\TextInput::make('reference')
                ->label('Referencia / Número de Operación')
                ->maxLength(100),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/'), 'create' => Pages\CreateInvoice::route('/create'), 'edit' => Pages\EditInvoice::route('/{record}/edit')];
    }
}
