<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('family_id')->label('Familia')->relationship('family', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('description')->label('Descripción')->required(),
            Forms\Components\TextInput::make('amount_usd')->label('Monto ($)')->numeric()->prefix('$')->required(),
            Forms\Components\TextInput::make('collected_amount_usd')->label('Recaudado ($)')->numeric()->prefix('$')->disabled(),
            Forms\Components\Select::make('status')->label('Estado')->options(['pending' => 'Pendiente', 'partial' => 'Parcial', 'collected' => 'Cobrada', 'cancelled' => 'Cancelada'])->required(),
            Forms\Components\DatePicker::make('due_date')->label('Vencimiento'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('family.name')->label('Familia')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->label('Descripción')->limit(30),
            Tables\Columns\TextColumn::make('amount_usd')->label('Monto ($)')->money('USD')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) { 'pending' => 'danger', 'partial' => 'warning', 'collected' => 'success', 'cancelled' => 'gray' })->formatStateUsing(fn (string $state): string => match ($state) { 'pending' => 'Pendiente', 'partial' => 'Parcial', 'collected' => 'Cobrada', 'cancelled' => 'Cancelada' }),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/'), 'create' => Pages\CreateInvoice::route('/create'), 'edit' => Pages\EditInvoice::route('/{record}/edit')];
    }
}
