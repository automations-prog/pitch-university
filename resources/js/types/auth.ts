import type { License } from '@/types/license';

export type UserRole = 'super_admin' | 'admin' | 'agent';

export type WorkLocation = 'onshore' | 'offshore';

export type UserTeam = {
    id: number;
    name: string;
    campaign: { id: number; name: string } | null;
    manager: string | null;
};

/** A team a user can be put on, as listed in user forms and filters. */
export type TeamOption = {
    id: number;
    name: string;
    campaign: { id: number; name: string };
};

export type UserStatus = 'active' | 'inactive';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: UserRole;
    status: UserStatus;
    work_location?: WorkLocation | null;
    team_id?: number | null;
    team?: UserTeam | null;
    /** What the signed-in user may do to this user (admin user pages only). */
    abilities?: { update: boolean; delete: boolean; impersonate: boolean };
    licenses_count: number;
    course_tracks_count?: number;
    licenses?: License[];
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: string[];
    impersonating: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
