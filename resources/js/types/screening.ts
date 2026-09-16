export type Screening = {
    id: number;
    token: string;
    public_url: string;
    responses_count: number;
    created_at: string;
};

export type ScreeningResponse = {
    id: number;
    screening_id: number;
    full_name: string;
    email: string;
    birthday: string;
    phone_number: string;
    public_url: string;
    created_at: string;
};
