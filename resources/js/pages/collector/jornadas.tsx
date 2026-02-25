import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Jornada, Payment, Wallet } from '@/types/collector';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Clock, Play, Square } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Cobrador', href: '/collector' },
    { title: 'Jornadas', href: '/collector/jornadas' },
];

interface Props {
    activeJornada: Jornada | null;
    pastJornadas: Jornada[];
    wallet: Wallet;
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

function paymentMethodLabel(method: Payment['payment_method']) {
    const labels = { cash: 'Efectivo', bank_transfer: 'Transferencia', mobile_payment: 'Pago Móvil' };
    return labels[method] ?? method;
}

export default function CollectorJornadas({ activeJornada, pastJornadas, wallet }: Props) {
    const page = usePage<{ flash?: { status?: string }; errors?: Record<string, string>; [key: string]: unknown }>();
    const flash = page.props.flash;
    const pageErrors = page.props.errors ?? {};

    const { data, setData, post, processing, errors } = useForm({
        notes: '',
    });

    function handleClose(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!activeJornada) return;
        post(`/collector/jornadas/${activeJornada.id}/close`);
    }

    function handleOpen() {
        router.post('/collector/jornadas/open');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jornadas" />

            <div className="mx-auto max-w-3xl space-y-6 p-4">
                {/* Flash messages */}
                {flash?.status === 'jornada-opened' && (
                    <div className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-200">
                        Jornada abierta correctamente. Los pagos que registres se vincularán automáticamente.
                    </div>
                )}
                {flash?.status === 'jornada-closed' && (
                    <div className="rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                        Jornada cerrada correctamente.
                    </div>
                )}
                {pageErrors.general && (
                    <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">
                        {pageErrors.general}
                    </div>
                )}

                {/* Back link */}
                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/collector">
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver al dashboard
                        </Link>
                    </Button>
                </div>

                {/* Wallet summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>Tu Wallet</CardTitle>
                        <CardDescription>Total acumulado de cobros</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-bold">${formatCurrency(wallet.balance)}</p>
                    </CardContent>
                </Card>

                {/* Active Jornada */}
                {activeJornada ? (
                    <Card className="border-yellow-500/50">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="h-5 w-5 text-yellow-500" />
                                        Jornada Activa #{activeJornada.id}
                                    </CardTitle>
                                    <CardDescription>
                                        Abierta el{' '}
                                        {new Date(activeJornada.opened_at).toLocaleString('es-VE')}
                                    </CardDescription>
                                </div>
                                <Badge variant="outline" className="text-yellow-600 border-yellow-500">
                                    Abierta
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Payments in this jornada */}
                            {activeJornada.payments && activeJornada.payments.length > 0 ? (
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
                                        {activeJornada.payments.map((payment) => (
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
                                            <TableCell colSpan={3} className="text-right font-semibold">
                                                Total
                                            </TableCell>
                                            <TableCell className="text-right font-bold">
                                                ${formatCurrency(Number(activeJornada.total_collected))}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-muted-foreground text-center text-sm py-4">
                                    Aún no hay pagos en esta jornada. Ve al dashboard para registrar cobros.
                                </p>
                            )}

                            {/* Close jornada form */}
                            <form onSubmit={handleClose} className="space-y-3 border-t pt-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notas de cierre (opcional)</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Observaciones de la jornada..."
                                        rows={2}
                                        maxLength={500}
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Square className="mr-1 h-4 w-4" />
                                    {processing ? 'Cerrando...' : 'Cerrar jornada'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <Play className="mx-auto mb-3 h-10 w-10 text-green-500" />
                            <p className="font-semibold">Sin jornada activa</p>
                            <p className="text-muted-foreground mb-4 text-sm">
                                Abre una jornada para agrupar los cobros que registres.
                            </p>
                            <Button onClick={handleOpen}>
                                <Play className="mr-1 h-4 w-4" />
                                Abrir nueva jornada
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {/* Past jornadas */}
                {pastJornadas.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Historial de Jornadas</CardTitle>
                            <CardDescription>Últimas 20 jornadas cerradas</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>Apertura</TableHead>
                                        <TableHead>Cierre</TableHead>
                                        <TableHead>Pagos</TableHead>
                                        <TableHead>Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pastJornadas.map((j) => (
                                        <TableRow key={j.id}>
                                            <TableCell className="text-muted-foreground">
                                                #{j.id}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(j.opened_at).toLocaleString('es-VE', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </TableCell>
                                            <TableCell>
                                                {j.closed_at
                                                    ? new Date(j.closed_at).toLocaleString('es-VE', {
                                                          day: '2-digit',
                                                          month: '2-digit',
                                                          hour: '2-digit',
                                                          minute: '2-digit',
                                                      })
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>{j.payments_count ?? 0}</TableCell>
                                            <TableCell className="font-mono">
                                                ${formatCurrency(Number(j.total_collected))}
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
