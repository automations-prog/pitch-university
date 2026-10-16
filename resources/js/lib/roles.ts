import type { UserRole, WorkLocation } from '@/types';

const ROLE_LABELS: Record<UserRole, string> = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    agent: 'Agent',
};

const WORK_LOCATION_LABELS: Record<WorkLocation, string> = {
    onshore: 'Onshore',
    offshore: 'Offshore',
};

export function roleLabel(role: UserRole): string {
    return ROLE_LABELS[role] ?? role;
}

export function workLocationLabel(location: WorkLocation): string {
    return WORK_LOCATION_LABELS[location] ?? location;
}
