import type { UserStatus } from '@/types/auth';
import type { License } from '@/types/license';
import type { CallRating } from '@/types/screening';

export type AgentProgress = {
    id: number;
    name: string;
    email: string;
    status: UserStatus;
    licenses?: License[];
    trainings_completed: number;
    total_trainings: number;
    average_score: number | null;
    last_activity: string | null;
};

export type DashboardStats = {
    total_agents: number;
    active_agents: number;
    inactive_agents: number;
    total_trainings: number;
};

export type DashboardFilterOption = {
    id: number;
    name: string;
};

export type ChartDatum = {
    name: string;
    value: number;
};

export type DashboardCharts = {
    status_split: ChartDatum[];
    completion: ChartDatum[];
    score_bands: ChartDatum[];
    per_license: ChartDatum[];
};

export type AgentProgressSummary = {
    trainings_completed: number;
    total_trainings: number;
    certifications: number;
    average_score: number | null;
    last_activity: string | null;
};

export type TrackStatus =
    | 'certified'
    | 'complete'
    | 'exam_next'
    | 'in_progress'
    | 'not_started';

export type AgentTrainingScore = {
    id: number;
    name: string;
    status: TrackStatus;
    average_score: number | null;
};

export type TrackOverviewRow = {
    slug: string;
    name: string;
    has_exam: boolean;
    average_score: number | null;
    assigned: number;
    not_started: number;
    in_progress: number;
    completed: number;
};

export type ScreeningOverview = {
    stats: {
        responses: number;
        called: number;
        awaiting_call: number;
        awaiting_review: number;
        reviewed: number;
    };
    gut_check: ChartDatum[];
    recent: {
        id: number;
        full_name: string;
        email: string;
        created_at: string;
        called_at: string | null;
        overall_gut_check: CallRating | null;
    }[];
};
