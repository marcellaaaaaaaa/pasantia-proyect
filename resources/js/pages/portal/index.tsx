import PortalLayout from '@/layouts/portal-layout';
import { Head, Link } from '@inertiajs/react';

interface Props {
    tenant: { name: string; slug: string };
}

export default function PortalIndex({ tenant }: Props) {
    return (
        <PortalLayout tenantName={tenant.name}>
            <Head title={`Portal · ${tenant.name}`} />

            <div style={{ padding: '80px 72px 96px', maxWidth: 1100, margin: '0 auto' }}>
                <div style={{ maxWidth: 680 }}>
                    {/* Badge */}
                    <span style={{
                        display: 'inline-flex', alignItems: 'center', gap: 6,
                        padding: '3px 12px', borderRadius: 999,
                        fontSize: 12, fontWeight: 600,
                        background: 'var(--portal-accent-soft)', color: 'var(--portal-accent)',
                        border: '1px solid color-mix(in oklch, var(--portal-accent) 25%, transparent)',
                        marginBottom: 20, letterSpacing: '-0.005em',
                    }}>
                        <span style={{ width: 6, height: 6, borderRadius: 999, background: 'var(--portal-accent)', display: 'inline-block' }} />
                        NUEVO · Consulta rápida
                    </span>

                    <h1 style={{
                        fontSize: 56, lineHeight: 1.05, margin: 0,
                        letterSpacing: '-0.035em', fontWeight: 600,
                    }}>
                        Un solo campo.<br />
                        <span style={{ color: 'var(--muted-foreground)' }}>Toda la información de la familia.</span>
                    </h1>

                    <p style={{
                        fontSize: 17, lineHeight: 1.55, color: 'var(--muted-foreground)',
                        marginTop: 20, maxWidth: 520,
                    }}>
                        Portal de verificación para personal de la alcaldía. Ingresa la cédula del jefe de
                        familia y obtén su expediente completo de servicios, pagos y facturas en segundos.
                    </p>

                    <div style={{ display: 'flex', gap: 10, marginTop: 32, flexWrap: 'wrap' }}>
                        <Link
                            href={`/portal/${tenant.slug}/consultar`}
                            style={{
                                display: 'inline-flex', alignItems: 'center', gap: 8,
                                height: 40, padding: '0 22px', borderRadius: 8,
                                fontSize: 14, fontWeight: 500,
                                background: 'var(--primary)', color: 'var(--primary-foreground)',
                                textDecoration: 'none', border: '1px solid transparent',
                            }}
                        >
                            Consultar por cédula
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </Link>
                    </div>
                </div>

                {/* Feature cards */}
                <div style={{
                    marginTop: 72,
                    display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16,
                }}>
                    {[
                        { icon: 'search', title: 'Búsqueda por cédula', desc: 'Sin navegar módulos. Un solo campo y enter.' },
                        { icon: 'clock', title: 'Línea de tiempo', desc: 'Facturas, pagos y avisos en orden cronológico.' },
                        { icon: 'shield', title: 'Solo lectura', desc: 'No modifica datos. Seguro para consulta pública interna.' },
                    ].map((f, i) => (
                        <div key={i} style={{
                            background: 'var(--card)', border: '1px solid var(--border)',
                            borderRadius: 12, boxShadow: '0 1px 2px rgba(0,0,0,0.03)',
                            padding: 20,
                        }}>
                            <div style={{
                                width: 32, height: 32, borderRadius: 8,
                                background: 'var(--muted)', display: 'grid', placeItems: 'center',
                                marginBottom: 12,
                            }}>
                                {f.icon === 'search' && <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>}
                                {f.icon === 'clock' && <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>}
                                {f.icon === 'shield' && <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2 4 6v6c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6l-8-4z"/></svg>}
                            </div>
                            <div style={{ fontWeight: 600, fontSize: 14, marginBottom: 4, letterSpacing: '-0.01em' }}>{f.title}</div>
                            <div style={{ fontSize: 13, color: 'var(--muted-foreground)', lineHeight: 1.5 }}>{f.desc}</div>
                        </div>
                    ))}
                </div>
            </div>
        </PortalLayout>
    );
}
