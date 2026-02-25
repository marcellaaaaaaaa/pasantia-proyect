export interface Sector {
    id: number;
    name: string;
    description: string | null;
}

export interface Property {
    id: number;
    sector_id: number;
    address: string;
    type: 'house' | 'apartment' | 'commercial';
    unit_number: string | null;
    sector?: Sector;
}

export interface Family {
    id: number;
    property_id: number;
    name: string;
    is_active: boolean;
    property?: Property;
}

export interface Service {
    id: number;
    name: string;
    description: string | null;
    default_price: number;
}

export interface BillingLine {
    id: number;
    service_id: number;
    amount: number;
    service?: Service;
}

export interface Billing {
    id: number;
    family_id: number;
    period: string; // "2026-02"
    amount: number;
    amount_paid: number;
    amount_pending: number;
    status: 'pending' | 'partial' | 'paid' | 'cancelled' | 'void';
    due_date: string;
    notes: string | null;
    family?: Family;
    lines?: BillingLine[];
}

export interface Payment {
    id: number;
    billing_id: number;
    collector_id: number;
    jornada_id: number | null;
    amount: number;
    payment_method: 'cash' | 'bank_transfer' | 'mobile_payment';
    status: 'paid' | 'reversed';
    reference: string | null;
    payment_date: string;
    notes: string | null;
    created_at: string;
    billing?: Billing;
}

export interface Wallet {
    id: number;
    user_id: number;
    balance: number;
}

export interface Jornada {
    id: number;
    collector_id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    notes: string | null;
    total_collected: number;
    created_at: string;
    payments?: Payment[];
    payments_count?: number;
}

/** Registro de pago capturado offline (IndexedDB) */
export interface OfflinePayment {
    offline_id: string;
    billing_id: number;
    amount: number;
    payment_method: 'cash' | 'bank_transfer' | 'mobile_payment';
    reference: string | null;
    notes: string | null;
    created_at: number; // timestamp ms
    synced: boolean;
}

export type SyncResult = {
    offline_id: string;
    status: 'synced' | 'conflict' | 'error';
    payment_id?: number;
    message?: string;
};
