export type Campaign = {
    id: number;
    name: string;
    is_active: boolean;
    teams_count?: number;
    agents_count?: number;
};

export type TeamManagerOption = {
    id: number;
    name: string;
    email: string;
    /** The team the admin runs now, if any. */
    team: string | null;
};

export type TeamRow = {
    id: number;
    name: string;
    campaign: { id: number; name: string };
    manager: string | null;
    agents_count: number;
};

export type TeamFormData = {
    id: number;
    name: string;
    campaign_id: number;
    manager_id: number | null;
};

export type RoleMatrixRole = {
    name: string;
    label: string;
    locked: boolean;
    permissions: string[];
};

export type PermissionOption = {
    name: string;
    label: string;
    group: string;
};

export type InvitationStatus = 'active' | 'expired' | 'revoked' | 'used_up';

export type InvitationRow = {
    id: number;
    url: string;
    status: InvitationStatus;
    team: { id: number; name: string };
    campaign: { id: number; name: string };
    created_by: string | null;
    uses_count: number;
    max_uses: number | null;
    expires_at: string | null;
    created_at: string;
};
