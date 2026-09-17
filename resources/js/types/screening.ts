export type Screening = {
    id: number;
    token: string;
    public_url: string;
    responses_count: number;
    created_at: string;
};

export type CallRating = 'yes' | 'somewhat' | 'no';

export type CallLog = {
    id: number;
    called_at: string | null;
    transcript: string | null;
    recording_path: string | null;
    recording_url: string | null;
    notes: string | null;
    clarity: CallRating | null;
    energy_tone: CallRating | null;
    composure_on_pushback: CallRating | null;
    overall_gut_check: CallRating | null;
    created_at: string;
};

export type ScreeningResponse = {
    id: number;
    screening_id: number;
    full_name: string;
    email: string;
    phone_number: string;
    public_url: string;
    call_log?: CallLog | null;
    created_at: string;
};
