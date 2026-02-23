import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { syncWithServer } from '@/lib/offline-db';
import collector from '@/routes/collector';
import type { BreadcrumbItem } from '@/types';
import type { Billing, Wallet } from '@/types/collector';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, RefreshCw, Wallet as WalletIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Cobrador', href: '/collector' },
];

interface Props {
    billings: Billing[];
    wallet: Wallet;
    pendingPaymentsCount: number;
}

function statusBadge(status: Billing['status']) {
    const variants = {
        pending: 'secondary',
        partial: 'outline',
        paid: 'default',
        cancelled: 'destructive',
        void: 'destructive',
    } as const;
    const labels = {
        pending: 'Pendiente',
        partial: 'Parcial',
        paid: 'Pagado',
        cancelled: 'Cancelado',
        void: 'Anulado',
    };
    return (
        <Badge variant={variants[status] ?? 'secondary'}>
            {labels[status] ?? status}
        </Badge>
    );
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export default function CollectorDashboard({ billings, wallet, pendingPaymentsCount }: Props) {
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;
    const [syncing, setSyncing] = useState(false);
    const [syncMsg, setSyncMsg] = useState<string | null>(null);

    async function handleSync() {
        setSyncing(true);
        setSyncMsg(null);
        try {
            const result = await syncWithServer();
            if (result.synced + result.conflicts + result.errors === 0) {
                setSyncMsg('No hay pagos offline pendientes de sincronizar.');
            } else {
                setSyncMsg(
                    `Sincronizados: ${result.synced} | Conflictos: ${result.conflicts} | Errores: ${result.errors}`,
                );
            }
        } catch (error) {
            console.error("[handleSync] sync failed:", error);
            setSyncMsg('Error al sincronizar. Verifica tu conexión.');
        } finally {
            setSyncing(false);
        }
    }

    const today = new Date().toISOString().slice(0, 10);
    const overdue = billings.filter((b) => b.due_date < today);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel Cobrador" />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Hola, {auth.user.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {billings.length} deudas pendientes en tu sector
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={handleSync} disabled={syncing}>
                            <RefreshCw className={`mr-1 h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
                            Sincronizar
                        </Button>
                        {pendingPaymentsCount > 0 && (
                            <Button asChild size="sm">
                                <Link href={collector.remittance().url}>
                                    Liquidar ({pendingPaymentsCount})
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {syncMsg && (
                    <p className="rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                        {syncMsg}
                    </p>
                )}

                {/* Wallet card */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Saldo en Wallet</CardTitle>
                            <WalletIcon className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">${formatCurrency(wallet.balance)}</p>
                            <p className="text-muted-foreground text-xs">
                                Efectivo custodiado pendiente de liquidar
                            </p>
                        </CardContent>
                    </Card>

                    {overdue.length > 0 && (
                        <Card className="border-destructive/50">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-destructive">
                                    Deudas Vencidas
                                </CardTitle>
                                <AlertCircle className="h-4 w-4 text-destructive" />
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold text-destructive">{overdue.length}</p>
                                <CardDescription>Requieren atención prioritaria</CardDescription>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Billings table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Deudas Pendientes</CardTitle>
                        <CardDescription>Haz clic en una fila para registrar el cobro</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {billings.length === 0 ? (
                            <p className="text-muted-foreground px-6 py-8 text-center text-sm">
                                Sin deudas pendientes en tu sector. ¡Buen trabajo!
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Familia</TableHead>
                                        <TableHead>Dirección</TableHead>
                                        <TableHead>Servicio</TableHead>
                                        <TableHead>Período</TableHead>
                                        <TableHead>Pendiente</TableHead>
                                        <TableHead>Vencimiento</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {billings.map((billing) => {
                                        const isOverdue = billing.due_date < today;
                                        return (
                                            <TableRow
                                                key={billing.id}
                                                className={isOverdue ? 'bg-destructive/5' : ''}
                                            >
                                                <TableCell className="font-medium">
                                                    {billing.family?.name ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {billing.family?.property?.address ?? '—'}
                                                </TableCell>
                                                <TableCell>{billing.service?.name ?? '—'}</TableCell>
                                                <TableCell>{billing.period}</TableCell>
                                                <TableCell className="font-mono">
                                                    ${formatCurrency(billing.amount_pending)}
                                                </TableCell>
                                                <TableCell
                                                    className={isOverdue ? 'font-semibold text-destructive' : ''}
                                                >
                                                    {billing.due_date}
                                                </TableCell>
                                                <TableCell>{statusBadge(billing.status)}</TableCell>
                                                <TableCell>
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link
                                                            href={collector.billing(billing.id).url}
                                                        >
                                                            Cobrar
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
