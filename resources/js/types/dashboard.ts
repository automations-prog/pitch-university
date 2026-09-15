import type { UserStatus } from '@/types/auth';
import type { License } from '@/types/license';

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
    average_score: number | null;
    last_activity: string | null;
};

export type AgentTrainingScore = {
    id: number;
    name: string;
    trainings_completed: number;
    average_score: number | null;
    last_activity: string | null;
};
