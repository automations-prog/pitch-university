export type VerticalTrainingStatus = 'active' | 'inactive';

export type VerticalTraining = {
    id: number;
    name: string;
    status: VerticalTrainingStatus;
    script_title: string | null;
    script_scenario: string | null;
    script_body: string | null;
    has_script: boolean;
    created_at: string;
};
