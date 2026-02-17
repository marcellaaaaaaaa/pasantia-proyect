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

export interface Billing {
    id: number;
    family_id: number;
    service_id: number;
    period: string; // "2026-02"
    amount: number;
    amount_paid: number;
    amount_pending: number;
    status: 'pending' | 'partial' | 'paid' | 'cancelled' | 'void';
    due_date: string;
    notes: string | null;
    family?: Family;
    service?: Service;
}

export interface Payment {
    id: number;
    billing_id: number;
    collector_id: number;
    amount: number;
    payment_method: 'cash' | 'bank_transfer' | 'mobile_payment';
    status: 'paid' | 'pending_remittance' | 'conciliated' | 'reversed';
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

export interface Remittance {
    id: number;
    collector_id: number;
    amount_declared: number;
    amount_confirmed: number | null;
    status: 'draft' | 'submitted' | 'approved' | 'rejected';
    submitted_at: string | null;
    reviewed_at: string | null;
    collector_notes: string | null;
    admin_notes: string | null;
    created_at: string;
    payments?: Payment[];
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
