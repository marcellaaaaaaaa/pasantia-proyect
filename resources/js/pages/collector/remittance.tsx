import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import collector from '@/routes/collector';
import type { BreadcrumbItem } from '@/types';
import type { Payment, Remittance, Wallet } from '@/types/collector';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Clock, XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Cobrador', href: '/collector' },
    { title: 'Liquidación', href: '/collector/remittance' },
];

interface Props {
    pendingPayments: Payment[];
    remittances: Remittance[];
    wallet: Wallet;
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

function remittanceStatusBadge(status: Remittance['status']) {
    const map = {
        draft: { label: 'Borrador', variant: 'secondary', icon: Clock },
        submitted: { label: 'Enviada', variant: 'outline', icon: Clock },
        approved: { label: 'Aprobada', variant: 'default', icon: CheckCircle },
        rejected: { label: 'Rechazada', variant: 'destructive', icon: XCircle },
    } as const;

    const { label, variant, icon: Icon } = map[status] ?? { label: status, variant: 'secondary', icon: Clock };

    return (
        <Badge variant={variant} className="flex items-center gap-1">
            <Icon className="h-3 w-3" />
            {label}
        </Badge>
    );
}

function paymentMethodLabel(method: Payment['payment_method']) {
    const labels = { cash: 'Efectivo', bank_transfer: 'Transferencia', mobile_payment: 'Pago Móvil' };
    return labels[method] ?? method;
}

export default function CollectorRemittance({ pendingPayments, remittances, wallet }: Props) {
    const page = usePage<{ flash?: { status?: string }; [key: string]: unknown }>();
    const flash = page.props.flash;

    const { data, setData, post, processing, errors } = useForm({
        notes: '',
    });

    function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(collector.remittance.create().url);
    }

    const totalPending = pendingPayments.reduce((sum, p) => sum + Number(p.amount), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Liquidación" />

            <div className="mx-auto max-w-3xl space-y-6 p-4">
                {/* Flash success */}
                {flash?.status === 'remittance-submitted' && (
                    <div className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-200">
                        Liquidación enviada correctamente. El administrador la revisará pronto.
                    </div>
                )}

                {/* Back link */}
                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={collector.dashboard().url}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver al dashboard
                        </Link>
                    </Button>
                </div>

                {/* Wallet summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>Tu Wallet</CardTitle>
                        <CardDescription>Efectivo acumulado pendiente de entregar</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-bold">${formatCurrency(wallet.balance)}</p>
                    </CardContent>
                </Card>

                {/* Create remittance */}
                {pendingPayments.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva Liquidación</CardTitle>
                            <CardDescription>
                                Se agruparán {pendingPayments.length} cobro(s) por un total de $
                                {formatCurrency(totalPending)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Pending payments list */}
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Familia</TableHead>
                                        <TableHead>Servicio</TableHead>
                                        <TableHead>Método</TableHead>
                                        <TableHead className="text-right">Monto</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pendingPayments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell>
                                                {payment.billing?.family?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {payment.billing?.service?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {paymentMethodLabel(payment.payment_method)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono">
                                                ${formatCurrency(Number(payment.amount))}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-right font-semibold"
                                        >
                                            Total declarado
                                        </TableCell>
                                        <TableCell className="text-right font-bold">
                                            ${formatCurrency(totalPending)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            {/* Notes + submit */}
                            <form onSubmit={handleSubmit} className="space-y-3 border-t pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notas (opcional)</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Observaciones para el administrador..."
                                        rows={2}
                                        maxLength={500}
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                                <InputError message={(errors as Record<string, string>).general} />
                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing ? 'Enviando...' : 'Enviar liquidación para revisión'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <CheckCircle className="mx-auto mb-3 h-10 w-10 text-green-500" />
                            <p className="font-semibold">Sin cobros pendientes de liquidar</p>
                            <p className="text-muted-foreground text-sm">
                                Todos tus cobros ya fueron incluidos en una liquidación.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Recent remittances */}
                {remittances.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de Liquidaciones</CardTitle>
                            <CardDescription>Últimas 10 liquidaciones</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Declarado</TableHead>
                                        <TableHead>Confirmado</TableHead>
                                        <TableHead>Estado</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {remittances.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell className="text-muted-foreground">
                                                #{r.id}
                                            </TableCell>
                                            <TableCell>
                                                {r.created_at
                                                    ? new Date(r.created_at).toLocaleDateString('es-VE')
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                ${formatCurrency(r.amount_declared)}
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                {r.amount_confirmed != null
                                                    ? `$${formatCurrency(r.amount_confirmed)}`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {remittanceStatusBadge(r.status)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
