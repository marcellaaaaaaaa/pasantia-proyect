<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Jobs\SendReceiptJob;
use App\Models\Family;
use App\Models\Payment;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * FIL-008 — PaymentResource
 *
 * Solo lectura para admin/super_admin. Incluye:
 *   - Tabla con columnas clave y filtros por estado/método/cobrador
 *   - Acción "Enviar comprobante": despacha SendReceiptJob y muestra la URL firmada
 *   - Vista de detalle con infolist completo
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Cobros';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pago';

    protected static ?string $pluralModelLabel = 'Pagos';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]); // Solo lectura — sin formulario
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('N° Pago')
                    ->formatStateUsing(fn (int $state): string => str_pad($state, 6, '0', STR_PAD_LEFT))
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing.family.name')
                    ->label('Familia')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing.lines.service.name')
                    ->label('Servicios')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3),

                Tables\Columns\TextColumn::make('billing.period')
                    ->label('Período')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash'           => 'success',
                        'bank_transfer'  => 'info',
                        'mobile_payment' => 'warning',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash'           => 'Efectivo',
                        'bank_transfer'  => 'Transferencia',
                        'mobile_payment' => 'Pago Móvil',
                        default          => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid'     => 'success',
                        'reversed' => 'gray',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid'     => 'Pagado',
                        'reversed' => 'Anulado',
                        default    => $state,
                    }),

                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('receipt_sent_at')
                    ->label('Comprobante')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Payment $record): string =>
                        $record->receipt_sent_at
                            ? 'Enviado el ' . $record->receipt_sent_at->format('d/m/Y H:i')
                            : 'No enviado'
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccione')
                    ->options([
                        'paid'     => 'Pagado',
                        'reversed' => 'Anulado',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->placeholder('Seleccione')
                    ->options([
                        'cash'           => 'Efectivo',
                        'bank_transfer'  => 'Transferencia',
                        'mobile_payment' => 'Pago Móvil',
                    ]),

                Tables\Filters\SelectFilter::make('collector')
                    ->label('Cobrador')
                    ->relationship('collector', 'name')
                    ->placeholder('Seleccione')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('family')
                    ->form([
                        Forms\Components\Select::make('family_id')
                            ->label('Familia')
                            ->placeholder('Seleccione')
                            ->options(fn () => Family::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['family_id'],
                        fn (Builder $q, $value) => $q->whereHas('billing', fn (Builder $bq) => $bq->where('family_id', $value)),
                    )),

                Tables\Filters\Filter::make('period')
                    ->form([
                        Forms\Components\TextInput::make('period')
                            ->label('Período (YYYY-MM)')
                            ->placeholder(now()->format('Y-m'))
                            ->regex('/^\d{4}-\d{2}$/'),
                    ])
                    ->query(fn ($query, array $data) =>
                        $query->when($data['period'], fn ($q, $v) =>
                            $q->whereHas('billing', fn ($bq) => $bq->where('period', $v))
                        )
                    ),

                Tables\Filters\Filter::make('sin_comprobante')
                    ->label('Sin comprobante enviado')
                    ->query(fn ($query) => $query->whereNull('receipt_sent_at')),

                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Comunidad')
                    ->relationship('tenant', 'name')
                    ->placeholder('Seleccione')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\Action::make('enviar_comprobante')
                    ->label('Enviar Comprobante')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar comprobante')
                    ->modalDescription('Se generará un enlace de descarga válido por 48 horas.')
                    ->action(function (Payment $record): void {
                        SendReceiptJob::dispatch($record->id);

                        $url = app(\App\Services\ReceiptService::class)->getSignedUrl($record);

                        Notification::make()
                            ->success()
                            ->title('Comprobante generado')
                            ->body("URL: {$url}")
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->defaultSort('payment_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Datos del Pago')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('N° Pago')
                            ->formatStateUsing(fn ($state) => str_pad($state, 6, '0', STR_PAD_LEFT)),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'paid'     => 'success',
                                'reversed' => 'gray',
                                default    => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'paid'     => 'Pagado',
                                'reversed' => 'Anulado',
                                default    => $state,
                            }),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('Monto')
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Método')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'cash'           => 'Efectivo',
                                'bank_transfer'  => 'Transferencia Bancaria',
                                'mobile_payment' => 'Pago Móvil',
                                default          => $state,
                            }),

                        Infolists\Components\TextEntry::make('payment_date')
                            ->label('Fecha de Pago')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('reference')
                            ->label('Referencia')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('collector.name')
                            ->label('Cobrador'),

                        Infolists\Components\TextEntry::make('receipt_sent_at')
                            ->label('Comprobante enviado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('No enviado'),

                        Infolists\Components\TextEntry::make('jornada.id')
                            ->label('Jornada')
                            ->placeholder('Sin jornada')
                            ->formatStateUsing(fn ($state) => $state ? "#{$state}" : null),
                    ]),

                Infolists\Components\Section::make('Cobro Asociado')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('billing.family.name')
                            ->label('Familia'),

                        Infolists\Components\TextEntry::make('billing.lines.service.name')
                            ->label('Servicios')
                            ->badge()
                            ->separator(', '),

                        Infolists\Components\TextEntry::make('billing.period')
                            ->label('Período'),

                        Infolists\Components\TextEntry::make('billing.amount')
                            ->label('Total del cobro')
                            ->money('USD'),

                        Infolists\Components\TextEntry::make('billing.status')
                            ->label('Estado del cobro')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'pending' => 'Pendiente',
                                'paid'    => 'Cobrado',
                                default   => $state,
                            }),
                    ]),

                Infolists\Components\Section::make('Notas')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view'  => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
