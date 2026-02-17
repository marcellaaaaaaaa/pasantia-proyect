<x-filament-panels::page>

    <div class="space-y-6">

        {{-- Descripción --}}
        <x-filament::section>
            <x-slot name="heading">Reporte Mensual de Cobros</x-slot>
            <x-slot name="description">
                Exporta el reporte de cobros y pagos del período seleccionado en formato Excel (.xlsx).
                El archivo contiene dos hojas: <strong>Cobros</strong> y <strong>Pagos</strong>.
            </x-slot>

            {{-- Formulario de selección de período --}}
            <form wire:submit="exportExcel">
                {{ $this->form }}

                <div class="mt-6">
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-arrow-down-tray"
                        color="success"
                        size="lg"
                    >
                        Descargar Excel
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Instrucciones de uso --}}
        <x-filament::section>
            <x-slot name="heading">¿Qué contiene el reporte?</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20">
                    <h3 class="font-semibold text-blue-800 dark:text-blue-300">Hoja 1: Cobros</h3>
                    <ul class="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-400">
                        <li>• Familia, inmueble, sector</li>
                        <li>• Servicio y período</li>
                        <li>• Monto y estado del cobro</li>
                        <li>• Fecha de vencimiento</li>
                    </ul>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-700 dark:bg-green-900/20">
                    <h3 class="font-semibold text-green-800 dark:text-green-300">Hoja 2: Pagos</h3>
                    <ul class="mt-2 space-y-1 text-sm text-green-700 dark:text-green-400">
                        <li>• Fecha y cobrador</li>
                        <li>• Monto y método de pago</li>
                        <li>• Estado del pago (en wallet / conciliado)</li>
                        <li>• Referencia bancaria si aplica</li>
                    </ul>
                </div>
            </div>
        </x-filament::section>

    </div>

</x-filament-panels::page>
