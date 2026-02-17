import Dexie, { type Table } from 'dexie';

import type { OfflinePayment, SyncResult } from '@/types/collector';

// ─── IndexedDB schema ─────────────────────────────────────────────────────────

export class CommunityERPDatabase extends Dexie {
    payments!: Table<OfflinePayment, string>;

    constructor() {
        super('communityerp');
        this.version(1).stores({
            // offline_id = primary key
            payments: 'offline_id, billing_id, synced, created_at',
        });
    }
}

export const db = new CommunityERPDatabase();

// ─── Helpers ──────────────────────────────────────────────────────────────────

export function generateOfflineId(): string {
    return `offline_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}

export async function addOfflinePayment(
    payment: Omit<OfflinePayment, 'offline_id' | 'created_at' | 'synced'>,
): Promise<string> {
    const offline_id = generateOfflineId();
    await db.payments.add({
        ...payment,
        offline_id,
        created_at: Date.now(),
        synced: false,
    });
    return offline_id;
}

export async function getPendingPayments(): Promise<OfflinePayment[]> {
    return db.payments.where('synced').equals(0).toArray();
}

export async function getPendingCount(): Promise<number> {
    return db.payments.where('synced').equals(0).count();
}

export async function markPaymentSynced(offline_id: string): Promise<void> {
    await db.payments.update(offline_id, { synced: true });
}

export async function clearSyncedPayments(): Promise<void> {
    await db.payments.where('synced').equals(1).delete();
}

// ─── Server sync ──────────────────────────────────────────────────────────────

export async function syncWithServer(): Promise<{
    synced: number;
    conflicts: number;
    errors: number;
}> {
    const pending = await getPendingPayments();
    if (pending.length === 0) return { synced: 0, conflicts: 0, errors: 0 };

    const csrf =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    const response = await fetch('/api/collector/payments/sync', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ payments: pending }),
    });

    if (!response.ok) {
        throw new Error(`Sync fallido: ${response.status} ${response.statusText}`);
    }

    const { results }: { results: SyncResult[] } = await response.json();

    let synced = 0;
    let conflicts = 0;
    let errors = 0;

    for (const result of results) {
        if (result.status === 'synced') {
            await markPaymentSynced(result.offline_id);
            synced++;
        } else if (result.status === 'conflict') {
            // Billing ya pagado: marcar como sync para no reintentar
            await markPaymentSynced(result.offline_id);
            conflicts++;
        } else {
            errors++;
        }
    }

    return { synced, conflicts, errors };
}
