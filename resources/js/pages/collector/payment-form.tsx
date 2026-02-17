import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { addOfflinePayment } from '@/lib/offline-db';
import collector from '@/routes/collector';
import type { BreadcrumbItem } from '@/types';
import type { Billing } from '@/types/collector';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, WifiOff } from 'lucide-react';
import { useState } from 'react';

interface Props {
    billing: Billing;
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

const paymentMethodLabels = {
    cash: 'Efectivo',
    bank_transfer: 'Transferencia bancaria',
    mobile_payment: 'Pago móvil',
} as const;

export default function PaymentForm({ billing }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel Cobrador', href: '/collector' },
        { title: billing.family?.name ?? 'Cobro', href: `/collector/billing/${billing.id}` },
    ];

    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [offlineSaved, setOfflineSaved] = useState(false);

    // Listen for connectivity changes
    if (typeof window !== 'undefined') {
        window.addEventListener('online', () => setIsOnline(true));
        window.addEventListener('offline', () => setIsOnline(false));
    }

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: billing.amount_pending.toString(),
        payment_method: 'cash' as 'cash' | 'bank_transfer' | 'mobile_payment',
        reference: '',
        notes: '',
    });

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (!isOnline) {
            // Save to IndexedDB for later sync
            try {
                await addOfflinePayment({
                    billing_id: billing.id,
                    amount: parseFloat(data.amount),
                    payment_method: data.payment_method,
                    reference: data.reference || null,
                    notes: data.notes || null,
                });
                setOfflineSaved(true);
                reset();
            } catch {
                alert('Error al guardar el pago offline. Intenta nuevamente.');
            }
            return;
        }

        post(`/collector/billing/${billing.id}`, {
            onSuccess: () => router.visit(collector.dashboard().url),
        });
    }

    if (offlineSaved) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Pago guardado offline" />
                <div className="flex flex-col items-center gap-4 p-8">
                    <WifiOff className="h-12 w-12 text-yellow-500" />
                    <h2 className="text-xl font-bold">Pago guardado localmente</h2>
                    <p className="text-muted-foreground max-w-sm text-center text-sm">
                        El pago se sincronizará automáticamente cuando recuperes la conexión. Puedes
                        continuar cobrando.
                    </p>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={collector.dashboard().url}>Volver al dashboard</Link>
                        </Button>
                        <Button onClick={() => setOfflineSaved(false)}>Registrar otro pago</Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Cobrar — ${billing.family?.name}`} />

            <div className="mx-auto max-w-2xl space-y-6 p-4">
                {/* Offline banner */}
                {!isOnline && (
                    <Alert variant="destructive">
                        <WifiOff className="h-4 w-4" />
                        <AlertTitle>Sin conexión</AlertTitle>
                        <AlertDescription>
                            El pago se guardará en este dispositivo y se sincronizará cuando
                            recuperes la conexión.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Billing info */}
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div>
                                <CardTitle>{billing.family?.name}</CardTitle>
                                <CardDescription>
                                    {billing.family?.property?.address}
                                    {billing.family?.property?.unit_number
                                        ? ` — Unidad ${billing.family.property.unit_number}`
                                        : ''}
                                </CardDescription>
                            </div>
                            <Badge variant={billing.status === 'partial' ? 'outline' : 'secondary'}>
                                {billing.status === 'partial' ? 'Pago parcial' : 'Pendiente'}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Servicio</dt>
                                <dd className="font-medium">{billing.service?.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Período</dt>
                                <dd className="font-medium">{billing.period}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Total</dt>
                                <dd className="font-medium">${formatCurrency(billing.amount)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Ya pagado</dt>
                                <dd className="font-medium">${formatCurrency(billing.amount_paid)}</dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-muted-foreground">Pendiente a cobrar</dt>
                                <dd className="text-lg font-bold text-green-600">
                                    ${formatCurrency(billing.amount_pending)}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Billing already paid warning */}
                {billing.status === 'paid' && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Deuda ya pagada</AlertTitle>
                        <AlertDescription>
                            Este billing ya fue pagado en su totalidad. No se puede registrar otro
                            pago.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Payment form */}
                {billing.status !== 'paid' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Registrar Cobro</CardTitle>
                            <CardDescription>
                                {!isOnline
                                    ? 'Se guardará offline y sincronizará al recuperar conexión'
                                    : 'El monto se acreditará a tu wallet inmediatamente'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                {/* Amount */}
                                <div className="grid gap-2">
                                    <Label htmlFor="amount">
                                        Monto a cobrar (máx. ${formatCurrency(billing.amount_pending)})
                                    </Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={billing.amount_pending}
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                {/* Payment method */}
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_method">Método de pago</Label>
                                    <Select
                                        value={data.payment_method}
                                        onValueChange={(v) =>
                                            setData(
                                                'payment_method',
                                                v as 'cash' | 'bank_transfer' | 'mobile_payment',
                                            )
                                        }
                                    >
                                        <SelectTrigger id="payment_method">
                                            <SelectValue placeholder="Selecciona método" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(paymentMethodLabels).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.payment_method} />
                                </div>

                                {/* Reference (optional, shown for non-cash) */}
                                {data.payment_method !== 'cash' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">Referencia (opcional)</Label>
                                        <Input
                                            id="reference"
                                            value={data.reference}
                                            onChange={(e) => setData('reference', e.target.value)}
                                            placeholder="N° de confirmación o referencia"
                                            maxLength={100}
                                        />
                                        <InputError message={errors.reference} />
                                    </div>
                                )}

                                {/* Notes */}
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notas (opcional)</Label>
                                    <Input
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Observaciones del cobro"
                                        maxLength={500}
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                {/* Error general */}
                                <InputError message={(errors as Record<string, string>).general} />

                                <div className="flex gap-2 pt-2">
                                    <Button type="button" variant="outline" asChild>
                                        <Link href={collector.dashboard().url}>
                                            <ArrowLeft className="mr-1 h-4 w-4" />
                                            Cancelar
                                        </Link>
                                    </Button>
                                    <Button type="submit" className="flex-1" disabled={processing}>
                                        {processing
                                            ? 'Registrando...'
                                            : !isOnline
                                              ? 'Guardar offline'
                                              : 'Registrar cobro'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
