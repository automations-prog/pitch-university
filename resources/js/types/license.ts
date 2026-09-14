export type LicenseStatus = 'active' | 'inactive';

export type License = {
    id: number;
    name: string;
    status: LicenseStatus;
    created_at: string;
};

export type LicenseStep = {
    id: number;
    title: string;
    description: string | null;
    order: number;
};
