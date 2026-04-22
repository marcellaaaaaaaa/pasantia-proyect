import PortalLayout from '@/layouts/portal-layout';
import type { PortalFamily } from '@/types/portal';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    tenant: { name: string; slug: string };
    family: PortalFamily;
    is_solvent: boolean;
}

type Tone = 'danger' | 'success' | 'accent' | 'warn' | 'default';
type FilterKey = 'all' | 'bill' | 'collection' | 'notice';

interface TimelineEvent {
    type: 'bill' | 'collection' | 'notice';
    date: string;
    datetime?: string;
    label: string;
    amount?: string;
    balance?: string;
    status?: string;
    statusTone?: Tone;
    service?: string;
    method?: string;
    reference?: string;
    detail?: string;
    invoiceId?: number;
    invoiceRef?: string;
}

const STATUS_LABEL: Record<string, string> = {
    pending: 'pendiente', approved: 'por cobrar', partial: 'parcial',
    paid: 'pagada', cancelled: 'cancelada', void: 'anulada',
};
const STATUS_TONE: Record<string, Tone> = {
    pending: 'warn', approved: 'warn', partial: 'warn',
    paid: 'success', cancelled: 'default', void: 'default',
};
const METHOD_LABEL: Record<string, string> = {
    cash: 'Efectivo · Taquilla',
    transfer: 'Transferencia bancaria',
    mobile_payment: 'Pago móvil',
};

const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function fmtDate(iso: string): string {
    const [y, m, d] = iso.split('-');
    return `${parseInt(d, 10)} ${MONTHS[parseInt(m, 10) - 1]} ${y}`;
}

function fmtDateTime(iso: string): string {
    const date = new Date(iso);
    const d = date.getDate();
    const m = date.getMonth();
    const y = date.getFullYear();
    const h = date.getHours().toString().padStart(2, '0');
    const min = date.getMinutes().toString().padStart(2, '0');
    return `${d} ${MONTHS[m]} ${y}, ${h}:${min}`;
}

function fmt(n: number | string) {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));
}

function badgeStyle(tone: Tone): React.CSSProperties {
    const map: Record<Tone, { bg: string; color: string; border: string }> = {
        success: { bg: 'var(--success-soft)', color: 'var(--success)', border: 'color-mix(in oklch, var(--success) 25%, transparent)' },
        danger:  { bg: 'var(--destructive-soft)', color: 'var(--destructive-foreground)', border: 'color-mix(in oklch, var(--destructive) 25%, transparent)' },
        warn:    { bg: 'var(--warn-soft)', color: 'var(--warn)', border: 'color-mix(in oklch, var(--warn) 30%, transparent)' },
        accent:  { bg: 'var(--portal-accent-soft)', color: 'var(--portal-accent)', border: 'color-mix(in oklch, var(--portal-accent) 25%, transparent)' },
        default: { bg: 'var(--muted)', color: 'var(--muted-foreground)', border: 'var(--border)' },
    };
    const t = map[tone];
    return {
        display: 'inline-flex', alignItems: 'center', gap: 5,
        padding: '2px 9px', borderRadius: 999,
        fontSize: 11, fontWeight: 600,
        background: t.bg, color: t.color, border: `1px solid ${t.border}`,
        letterSpacing: '-0.005em', whiteSpace: 'nowrap' as const,
    };
}

function typePill(type: TimelineEvent['type']): React.CSSProperties {
    const map = {
        bill:    { bg: 'var(--muted)',         color: 'var(--muted-foreground)' },
        collection: { bg: 'var(--success-soft)',  color: 'var(--success)' },
        notice:  { bg: 'var(--warn-soft)',     color: 'var(--warn)' },
    };
    const s = map[type];
    return {
        display: 'inline-block', padding: '1px 6px', borderRadius: 4,
        fontSize: 10, fontWeight: 700, letterSpacing: '0.07em',
        textTransform: 'uppercase' as const,
        background: s.bg, color: s.color,
    };
}

function heroBg(tone: Tone) {
    if (tone === 'danger')  return 'var(--destructive-soft)';
    if (tone === 'success') return 'var(--success-soft)';
    if (tone === 'accent')  return 'var(--portal-accent-soft)';
    return 'var(--muted)';
}

function Avatar({ name, size = 52 }: { name: string; size?: number }) {
    const initials = name.split(' ').slice(0, 2).map(s => s[0]).join('').toUpperCase();
    return (
        <div style={{
            width: size, height: size, borderRadius: '50%',
            background: 'var(--portal-accent-soft)', color: 'var(--portal-accent)',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 600, fontSize: size * 0.36, letterSpacing: '-0.02em',
            border: '1px solid color-mix(in oklch, var(--portal-accent) 15%, transparent)',
            flexShrink: 0,
        }}>{initials}</div>
    );
}

function buildTimeline(family: PortalFamily): TimelineEvent[] {
    const events: TimelineEvent[] = [];

    for (const invoice of family.invoices) {
        events.push({
            type: 'bill',
            date: invoice.due_date,
            datetime: invoice.created_at,
            label: invoice.description,
            amount: `$${fmt(invoice.amount_usd)}`,
            balance: invoice.status === 'partial' ? `$${fmt(invoice.balance_usd)}` : undefined,
            status: STATUS_LABEL[invoice.status] ?? invoice.status,
            statusTone: STATUS_TONE[invoice.status] ?? 'default',
            service: invoice.service?.name,
            invoiceId: invoice.id,
        });

        for (const col of (invoice.collections ?? [])) {
            events.push({
                type: 'collection',
                date: col.collected_at.slice(0, 10),
                datetime: col.created_at,
                label: 'Cobro registrado',
                amount: `$${fmt(col.amount_usd)}`,
                method: METHOD_LABEL[col.method] ?? col.method,
                reference: col.reference ?? undefined,
                invoiceId: invoice.id,
                invoiceRef: invoice.description,
            });
        }
    }

    if (family.is_exonerated && family.exonerated_at) {
        events.push({
            type: 'notice',
            date: family.exonerated_at.slice(0, 10),
            datetime: family.exonerated_at,
            label: 'Exoneración aprobada',
            detail: family.exoneration_reason ?? undefined,
        });
    }

    return events.sort((a, b) => {
        const da = a.datetime ?? `${a.date}T00:00:00Z`;
        const db = b.datetime ?? `${b.date}T00:00:00Z`;
        return db.localeCompare(da);
    });
}

function TimelineIcon({ type }: { type: TimelineEvent['type'] }) {
    if (type === 'collection') return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 6 9 17l-5-5"/>
        </svg>
    );
    if (type === 'notice') return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
    );
    return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M4 3h16v18l-3-2-3 2-3-2-3 2-4-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>
        </svg>
    );
}

function iconStyle(type: TimelineEvent['type']): React.CSSProperties {
    if (type === 'collection') return { background: 'var(--success-soft)', color: 'var(--success)', border: '1px solid var(--border)' };
    if (type === 'notice')  return { background: 'var(--warn-soft)', color: 'var(--warn)', border: '1px solid var(--border)' };
    return { background: 'var(--muted)', color: 'var(--muted-foreground)', border: '1px solid var(--border)' };
}

function EventCard({ ev }: { ev: TimelineEvent }) {
    const hasSubInfo = ev.type === 'bill' || ev.method || ev.reference || ev.invoiceRef || ev.detail;

    return (
        <div style={{
            background: 'var(--card)', border: '1px solid var(--border)',
            borderRadius: 12, padding: '11px 14px',
        }}>
            {/* Row 1: type pill + invoice id + service  ·  status or cobro amount */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6, gap: 8 }}>
                <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' as const, minWidth: 0 }}>
                    <span style={typePill(ev.type)}>
                        {ev.type === 'bill' ? 'Factura' : ev.type === 'collection' ? 'Cobro' : 'Aviso'}
                    </span>
                    {ev.invoiceId && (
                        <span style={{ fontSize: 12, color: 'var(--foreground)', fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>
                            #{ev.invoiceId}
                        </span>
                    )}
                    {ev.service && (
                        <span style={{ fontSize: 12, color: 'var(--muted-foreground)' }}>· {ev.service}</span>
                    )}
                </div>
                {ev.type === 'collection' ? (
                    <span style={{
                        fontSize: 16, fontWeight: 700, color: 'var(--success)',
                        letterSpacing: '-0.02em', fontVariantNumeric: 'tabular-nums', flexShrink: 0,
                    }}>
                        +{ev.amount}
                    </span>
                ) : ev.status ? (
                    <span style={badgeStyle(ev.statusTone ?? 'default')}>{ev.status}</span>
                ) : null}
            </div>

            {/* Row 2: main label  ·  bill total + saldo */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                <div style={{ fontSize: 14, fontWeight: 600, letterSpacing: '-0.01em', flex: 1, minWidth: 0, lineHeight: 1.3 }}>
                    {ev.label}
                </div>
                {ev.type === 'bill' && ev.amount && (
                    <div style={{ textAlign: 'right', flexShrink: 0 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, fontVariantNumeric: 'tabular-nums', letterSpacing: '-0.02em' }}>
                            {ev.amount}
                        </div>
                        {ev.balance && (
                            <div style={{ fontSize: 11, color: 'var(--warn)', fontWeight: 600, marginTop: 1 }}>
                                Saldo {ev.balance}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Row 3: sub-details */}
            {hasSubInfo && (
                <div style={{
                    marginTop: 6, paddingTop: 6,
                    borderTop: '1px solid var(--border)',
                    fontSize: 12, color: 'var(--muted-foreground)',
                    display: 'flex', flexWrap: 'wrap' as const, gap: '3px 12px', alignItems: 'center',
                }}>
                    {ev.type === 'bill' && (
                        <span>Vence {fmtDate(ev.date)}</span>
                    )}
                    {ev.method && <span>{ev.method}</span>}
                    {ev.reference && (
                        <span style={{ fontVariantNumeric: 'tabular-nums' }}>
                            Ref.&nbsp;{ev.reference}
                        </span>
                    )}
                    {ev.invoiceRef && ev.invoiceId && (
                        <span>Fac. #{ev.invoiceId} · {ev.invoiceRef}</span>
                    )}
                    {ev.detail && <span>{ev.detail}</span>}
                </div>
            )}
        </div>
    );
}

export default function PortalFamily({ tenant, family, is_solvent }: Props) {
    const [filter, setFilter] = useState<FilterKey>('all');

    const tone: Tone = family.is_exonerated ? 'accent' : is_solvent ? 'success' : 'danger';
    const statusLabel = family.is_exonerated ? 'Exonerada' : is_solvent ? 'Solvente' : 'Moroso';

    const primaryContact = family.people.find(p => p.is_primary_contact) ?? family.people[0];
    const totalPending = family.invoices
        .filter(i => ['pending', 'approved', 'partial'].includes(i.status))
        .reduce((s, i) => s + Number(i.balance_usd), 0);

    const allEvents = buildTimeline(family);
    const filtered = filter === 'all' ? allEvents : allEvents.filter(e => e.type === filter);

    const monthlyTotal = family.services.reduce((s, sv) => s + Number(sv.default_price_usd), 0);

    const filterBtns: { k: FilterKey; label: string }[] = [
        { k: 'all',     label: 'Todos' },
        { k: 'bill',    label: 'Facturas' },
        { k: 'collection', label: 'Cobros' },
        { k: 'notice',  label: 'Avisos' },
    ];

    return (
        <PortalLayout tenantName={tenant.name} showBack backHref={`/portal/${tenant.slug}/consultar`} backLabel="Volver">
            <Head title={`${family.name} · ${tenant.name}`} />

            {/* StatusHero */}
            <div style={{
                padding: '32px 36px',
                borderBottom: '1px solid var(--border)',
                background: heroBg(tone),
            }}>
                <div style={{ display: 'flex', gap: 24, alignItems: 'center', maxWidth: 1100, margin: '0 auto' }}>
                    {primaryContact && <Avatar name={primaryContact.full_name} size={64} />}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 6, flexWrap: 'wrap' }}>
                            <span style={badgeStyle(tone)}>
                                {tone === 'success' && <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5"/></svg>}
                                {tone === 'danger'  && <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>}
                                {tone === 'accent'  && <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2 4 6v6c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6l-8-4z"/></svg>}
                                {statusLabel}
                            </span>
                            {primaryContact && (
                                <span style={{ fontSize: 12, color: 'var(--muted-foreground)', fontVariantNumeric: 'tabular-nums' }}>
                                    {primaryContact.id_number}
                                </span>
                            )}
                        </div>
                        <h1 style={{ fontSize: 28, margin: 0, letterSpacing: '-0.025em', fontWeight: 600, lineHeight: 1.15 }}>
                            {family.name}
                        </h1>
                        <div style={{ fontSize: 13, color: 'var(--muted-foreground)', marginTop: 4 }}>
                            {primaryContact?.full_name ?? '—'} · {family.people.length} integrante{family.people.length !== 1 ? 's' : ''}
                        </div>
                    </div>
                    {/* Amount */}
                    <div style={{
                        textAlign: 'right', paddingLeft: 24,
                        borderLeft: '1px solid color-mix(in oklch, var(--foreground) 10%, transparent)',
                    }}>
                        <div style={{ fontSize: 11, color: 'var(--muted-foreground)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 600 }}>
                            {tone === 'danger' ? 'Deuda acumulada' : tone === 'accent' ? 'Exoneración activa' : 'Deuda total'}
                        </div>
                        <div style={{
                            fontSize: 32, fontWeight: 600, letterSpacing: '-0.02em', marginTop: 2,
                            fontVariantNumeric: 'tabular-nums',
                            color: tone === 'danger' ? 'var(--destructive-foreground)' : tone === 'success' ? 'var(--success)' : 'var(--portal-accent)',
                        }}>
                            ${fmt(totalPending)}
                        </div>
                        {tone === 'danger' && totalPending > 0 && (
                            <div style={{ fontSize: 12, color: 'var(--destructive-foreground)', marginTop: 2 }}>
                                {family.invoices.filter(i => ['pending','approved','partial'].includes(i.status)).length} facturas pendientes
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* InfoGrid */}
            <div style={{
                display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)',
                borderBottom: '1px solid var(--border)',
                maxWidth: 1100, margin: '0 auto', width: '100%',
            }}>
                {[
                    { label: 'Dirección', value: family.property?.address ?? '—' },
                    { label: 'Sector', value: family.property?.sector?.name ?? '—' },
                    { label: 'Servicios activos', value: family.services.map(s => s.name).join(' · ') || '—' },
                    { label: 'Facturación mensual', value: `$${fmt(monthlyTotal)}/mes`, mono: true },
                ].map((it, i) => (
                    <div key={i} style={{
                        padding: '18px 24px',
                        borderRight: i < 3 ? '1px solid var(--border)' : 'none',
                    }}>
                        <div style={{ fontSize: 11, color: 'var(--muted-foreground)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 600, marginBottom: 4 }}>
                            {it.label}
                        </div>
                        <div style={{ fontSize: 14, fontWeight: 500, letterSpacing: '-0.005em', fontVariantNumeric: it.mono ? 'tabular-nums' : undefined }}>
                            {it.value}
                        </div>
                    </div>
                ))}
            </div>

            {/* Exoneration banner */}
            {family.is_exonerated && family.exoneration_reason && (
                <div style={{
                    padding: '14px 36px', maxWidth: 1100, margin: '0 auto', width: '100%',
                    background: 'var(--portal-accent-soft)',
                    fontSize: 13, color: 'var(--portal-accent)',
                    display: 'flex', gap: 8, alignItems: 'center',
                    borderBottom: '1px solid var(--border)',
                }}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 2 4 6v6c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6l-8-4z"/>
                    </svg>
                    <strong>Exoneración vigente:</strong>&nbsp;{family.exoneration_reason}
                </div>
            )}

            {/* Timeline */}
            <div style={{ padding: '32px 36px 48px', maxWidth: 1100, margin: '0 auto' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, gap: 10, flexWrap: 'wrap' }}>
                    <div>
                        <h2 style={{ fontSize: 20, margin: 0, letterSpacing: '-0.02em', fontWeight: 600 }}>Línea de tiempo</h2>
                        <div style={{ fontSize: 13, color: 'var(--muted-foreground)', marginTop: 2 }}>
                            {allEvents.length} eventos · {family.invoices.length} facturas · {family.invoices.reduce((s, i) => s + (i.collections?.length ?? 0), 0)} cobros
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        {filterBtns.map(o => (
                            <button
                                key={o.k}
                                onClick={() => setFilter(o.k)}
                                style={{
                                    height: 32, padding: '0 12px', borderRadius: 8,
                                    fontSize: 13, fontWeight: 500, cursor: 'pointer',
                                    fontFamily: 'inherit', transition: 'all .15s',
                                    background: filter === o.k ? 'var(--primary)' : 'var(--background)',
                                    color: filter === o.k ? 'var(--primary-foreground)' : 'var(--foreground)',
                                    border: filter === o.k ? '1px solid transparent' : '1px solid var(--border)',
                                }}
                            >{o.label}</button>
                        ))}
                    </div>
                </div>

                {filtered.length === 0 ? (
                    <div style={{
                        background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12,
                        padding: 40, textAlign: 'center',
                        fontSize: 14, color: 'var(--muted-foreground)',
                    }}>
                        Sin eventos en esta categoría
                    </div>
                ) : (
                    <div>
                        {filtered.map((ev, i) => (
                            <div key={i} style={{ display: 'flex', gap: 16, position: 'relative' }}>
                                {/* Icon + connecting line */}
                                <div style={{ position: 'relative', flexShrink: 0 }}>
                                    <div style={{
                                        width: 32, height: 32, borderRadius: 999,
                                        display: 'grid', placeItems: 'center',
                                        zIndex: 1, position: 'relative',
                                        ...iconStyle(ev.type),
                                    }}>
                                        <TimelineIcon type={ev.type} />
                                    </div>
                                    {i < filtered.length - 1 && (
                                        <div style={{
                                            position: 'absolute', left: 15, top: 32,
                                            bottom: -16, width: 1,
                                            background: 'var(--border)',
                                        }} />
                                    )}
                                </div>

                                {/* Content */}
                                <div style={{ flex: 1, paddingBottom: 20, minWidth: 0 }}>
                                    {/* Timestamp above card */}
                                    <div style={{ fontSize: 12, color: 'var(--muted-foreground)', fontWeight: 500, marginBottom: 5 }}>
                                        {ev.datetime ? fmtDateTime(ev.datetime) : fmtDate(ev.date)}
                                    </div>
                                    <EventCard ev={ev} />
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Action buttons */}
                <div style={{ display: 'flex', gap: 8, marginTop: 24, flexWrap: 'wrap' }}>
                    <button style={{
                        height: 32, padding: '0 12px', borderRadius: 8, fontSize: 13, fontWeight: 500,
                        background: 'var(--background)', color: 'var(--foreground)', border: '1px solid var(--border)',
                        cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: 6,
                    }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 3v12M6 9l6 6 6-6M4 21h16"/>
                        </svg>
                        Exportar PDF
                    </button>
                    <button style={{
                        height: 32, padding: '0 12px', borderRadius: 8, fontSize: 13, fontWeight: 500,
                        background: 'var(--background)', color: 'var(--foreground)', border: '1px solid var(--border)',
                        cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center', gap: 6,
                    }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/>
                        </svg>
                        Imprimir
                    </button>
                    <Link
                        href={`/portal/${tenant.slug}/consultar`}
                        style={{
                            height: 32, padding: '0 12px', borderRadius: 8, fontSize: 13, fontWeight: 500,
                            background: 'transparent', color: 'var(--foreground)', border: '1px solid transparent',
                            cursor: 'pointer', fontFamily: 'inherit', display: 'inline-flex', alignItems: 'center',
                            textDecoration: 'none',
                        }}
                    >
                        Nueva consulta
                    </Link>
                </div>
            </div>
        </PortalLayout>
    );
}
