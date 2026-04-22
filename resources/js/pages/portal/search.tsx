import PortalLayout from '@/layouts/portal-layout';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    tenant: { name: string; slug: string };
}

export default function PortalSearch({ tenant }: Props) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [cedula, setCedula] = useState('');
    const [processing, setProcessing] = useState(false);

    const cedulaError = errors?.cedula ?? null;

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        if (!cedula.trim()) return;
        setProcessing(true);
        router.get(
            `/portal/${tenant.slug}/buscar`,
            { cedula },
            {
                preserveState: true,
                onFinish: () => setProcessing(false),
            }
        );
    }

    return (
        <PortalLayout tenantName={tenant.name} showBack backHref={`/portal/${tenant.slug}`} backLabel="Volver">
            <Head title={`Consultar · ${tenant.name}`} />

            <div style={{
                padding: '96px 72px',
                display: 'grid', placeItems: 'center', minHeight: 640,
            }}>
                <div style={{ width: '100%', maxWidth: 440 }}>
                    {/* Icon + title */}
                    <div style={{ textAlign: 'center', marginBottom: 32 }}>
                        <div style={{
                            width: 48, height: 48, borderRadius: 12,
                            background: 'var(--portal-accent-soft)', color: 'var(--portal-accent)',
                            display: 'grid', placeItems: 'center', margin: '0 auto 18px',
                        }}>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                            </svg>
                        </div>
                        <h2 style={{ fontSize: 28, margin: 0, letterSpacing: '-0.025em', fontWeight: 600 }}>
                            Consulta por cédula
                        </h2>
                        <p style={{ fontSize: 14, color: 'var(--muted-foreground)', marginTop: 8, lineHeight: 1.5 }}>
                            Ingresa la cédula del jefe de familia registrado.
                        </p>
                    </div>

                    {/* Card form */}
                    <div style={{
                        background: 'var(--card)', border: '1px solid var(--border)',
                        borderRadius: 12, boxShadow: '0 1px 2px rgba(0,0,0,0.03)',
                        padding: 24,
                    }}>
                        <form onSubmit={handleSearch}>
                            <label style={{ fontSize: 13, fontWeight: 500, display: 'block', marginBottom: 8 }}>
                                Cédula de identidad
                            </label>

                            <div style={{
                                display: 'flex', alignItems: 'center',
                                border: `1px solid ${cedulaError ? 'var(--destructive)' : 'var(--border)'}`,
                                borderRadius: 8, background: 'var(--background)',
                                padding: '0 14px', gap: 8,
                                transition: 'border-color .15s',
                            }}>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--muted-foreground)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                                </svg>
                                <input
                                    value={cedula}
                                    onChange={e => setCedula(e.target.value)}
                                    placeholder="V-12.345.678"
                                    autoFocus
                                    autoComplete="off"
                                    style={{
                                        flex: 1, border: 'none', outline: 'none', background: 'transparent',
                                        height: 44, fontSize: 15, fontFamily: 'inherit',
                                        color: 'var(--foreground)', letterSpacing: '0.01em',
                                    }}
                                />
                            </div>

                            {cedulaError && (
                                <div style={{
                                    fontSize: 12, color: 'var(--destructive-foreground)', marginTop: 8,
                                    display: 'flex', alignItems: 'center', gap: 6,
                                    background: 'var(--destructive-soft)',
                                    border: '1px solid color-mix(in oklch, var(--destructive) 25%, transparent)',
                                    borderRadius: 6, padding: '6px 10px',
                                }}>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                                        <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>
                                    </svg>
                                    {cedulaError}
                                </div>
                            )}

                            {!cedulaError && (
                                <div style={{ fontSize: 12, color: 'var(--muted-foreground)', marginTop: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>
                                    </svg>
                                    Formato: V-12345678 o E-12345678
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={processing || !cedula.trim()}
                                style={{
                                    width: '100%', marginTop: 18,
                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                                    height: 40, borderRadius: 8, fontSize: 14, fontWeight: 500,
                                    background: processing || !cedula.trim() ? 'var(--muted)' : 'var(--primary)',
                                    color: processing || !cedula.trim() ? 'var(--muted-foreground)' : 'var(--primary-foreground)',
                                    border: '1px solid transparent', cursor: processing || !cedula.trim() ? 'not-allowed' : 'pointer',
                                    fontFamily: 'inherit', transition: 'all .15s',
                                }}
                            >
                                {processing ? 'Buscando...' : 'Buscar expediente'}
                                {!processing && (
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M5 12h14M13 6l6 6-6 6"/>
                                    </svg>
                                )}
                            </button>
                        </form>
                    </div>

                    <div style={{ marginTop: 20, fontSize: 12, color: 'var(--muted-foreground)', textAlign: 'center', lineHeight: 1.5 }}>
                        Consulta restringida a personal autorizado de la alcaldía.<br />
                        Todas las búsquedas quedan registradas en el log de auditoría.
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
