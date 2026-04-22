import { useAppearance } from '@/hooks/use-appearance';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Moon, Sun } from 'lucide-react';

interface Props {
    children: React.ReactNode;
    tenantName: string;
    showBack?: boolean;
    backHref?: string;
    backLabel?: string;
}

export default function PortalLayout({ children, tenantName, showBack = false, backHref = '/', backLabel = 'Volver' }: Props) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    return (
        <div className="min-h-screen" style={{ background: 'var(--background)', color: 'var(--foreground)', fontFamily: 'inherit' }}>
            {/* Header */}
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                padding: '16px 28px',
                borderBottom: '1px solid var(--border)',
                background: 'var(--background)',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <div style={{
                        width: 28, height: 28, borderRadius: 7,
                        background: 'var(--primary)', color: 'var(--primary-foreground)',
                        display: 'grid', placeItems: 'center',
                        fontWeight: 700, fontSize: 14, letterSpacing: '-0.03em',
                    }}>P</div>
                    <div style={{ lineHeight: 1.1 }}>
                        <div style={{ fontWeight: 600, fontSize: 14, letterSpacing: '-0.015em' }}>{tenantName}</div>
                        <div style={{ fontSize: 11, color: 'var(--muted-foreground)' }}>Portal de Consulta</div>
                    </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    {showBack && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={backHref}>
                                <ArrowLeft className="mr-1 h-3.5 w-3.5" />
                                {backLabel}
                            </Link>
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')}
                        title={resolvedAppearance === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
                    >
                        {resolvedAppearance === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                    </Button>
                    <span style={{ fontSize: 12, color: 'var(--muted-foreground)' }}>Alcaldía · v1.0</span>
                </div>
            </div>

            {children}

            {/* Footer */}
            <div style={{
                padding: '16px 36px',
                borderTop: '1px solid var(--border)',
                fontSize: 12, color: 'var(--muted-foreground)',
                display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap',
            }}>
                <span>© 2026 {tenantName} · Sistema de Gestión Comunitaria</span>
                <span>Solo lectura · Los datos son confidenciales</span>
            </div>
        </div>
    );
}
