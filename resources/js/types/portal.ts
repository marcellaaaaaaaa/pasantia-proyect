export interface PortalSector {
    id: number;
    name: string;
}

export interface PortalProperty {
    id: number;
    address: string;
    type: 'house' | 'apartment' | 'commercial';
    unit_number: string | null;
    sector?: PortalSector;
}

export interface PortalPerson {
    id: number;
    full_name: string;
    id_number: string;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    is_primary_contact: boolean;
}

export interface PortalService {
    id: number;
    name: string;
    type: string;
    default_price_usd: number;
    is_active: boolean;
}

export interface PortalCollection {
    id: number;
    amount_usd: number;
    currency: string;
    method: 'cash' | 'transfer' | 'mobile_payment';
    reference: string | null;
    notes: string | null;
    collected_at: string;
    created_at: string;
}

export interface PortalInvoice {
    id: number;
    description: string;
    amount_usd: string;
    collected_amount_usd: string;
    balance_usd: number;
    status: 'pending' | 'approved' | 'partial' | 'paid' | 'cancelled' | 'void';
    due_date: string;
    created_at: string;
    service?: { id: number; name: string };
    collections?: PortalCollection[];
}

export interface PortalFamily {
    id: number;
    name: string;
    is_active: boolean;
    is_exonerated: boolean;
    exoneration_reason: string | null;
    exonerated_at?: string | null;
    property?: PortalProperty;
    people: PortalPerson[];
    services: PortalService[];
    exonerated_services: PortalService[];
    invoices: PortalInvoice[];
}
