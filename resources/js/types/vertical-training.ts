import type { License } from '@/types/license';

export type VerticalTrainingStatus = 'active' | 'inactive';

export type VerticalTraining = {
    id: number;
    name: string;
    status: VerticalTrainingStatus;
    license_id: number | null;
    license?: License | null;
    script_title: string | null;
    script_scenario: string | null;
    script_body: string | null;
    has_script: boolean;
    created_at: string;
};
