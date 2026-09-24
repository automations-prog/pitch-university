export type CourseTrack = {
    slug: string;
    name: string;
    description: string | null;
};

export type CourseTrackProgress = {
    passed_modules: number;
    total_modules: number;
    has_exam?: boolean;
    is_certified?: boolean;
};

export type CourseTrackOption = {
    id: number;
    name: string;
};

export type CourseBadge = {
    id: string;
    name: string;
    icon: string;
};

export type CourseModule = {
    slug: string;
    title: string;
    summary: string | null;
    est_minutes: number;
    badge: CourseBadge | null;
};

export type CourseModuleProgress = {
    is_unlocked: boolean;
    is_quiz_unlocked: boolean;
    is_passed: boolean;
    completed_lessons: number;
    total_lessons: number;
    best_score: number | null;
    attempts_count: number;
};

export type CourseLessonSummary = {
    slug: string;
    title: string;
    est_minutes: number;
};

export type CourseTextBlock = {
    type: 'text';
    html: string;
};

export type CourseCalloutBlock = {
    type: 'callout';
    tone: 'tip' | 'warning' | 'compliance' | string;
    title?: string;
    html: string;
};

export type CourseCheckBlock = {
    type: 'check';
    question: string;
    choices: string[];
    answer_index: number;
    explanation?: string;
};

export type CourseScriptLine = {
    speaker: 'agent' | 'consumer' | 'note' | string;
    text: string;
};

export type CourseScriptBlock = {
    type: 'script';
    title?: string;
    lines: CourseScriptLine[];
};

export type CourseRebuttalBlock = {
    type: 'rebuttal';
    objection: string;
    response: string;
    coach_note?: string;
};

export type CourseBlock =
    | CourseTextBlock
    | CourseCalloutBlock
    | CourseCheckBlock
    | CourseScriptBlock
    | CourseRebuttalBlock
    | { type: string };

export type CourseLesson = CourseLessonSummary & {
    blocks: CourseBlock[];
    is_complete: boolean;
};

export type CourseQuizQuestion = {
    id: string;
    question: string;
    choices: string[];
};

export type CourseQuizResult = {
    id: string;
    selected_index: number | null;
    answer_index: number;
    is_correct: boolean;
    explanation: string | null;
};

export type CourseQuizAttempt = {
    score_pct: number;
    passed: boolean;
    correct_count: number;
    created_at: string;
    results: CourseQuizResult[];
};

export type CourseUserModuleScore = {
    best_score: number;
    passed: boolean;
    attempts_count: number;
} | null;

export type CourseUserProgress = {
    id: number;
    name: string;
    email: string;
    is_assigned: boolean;
    completed_lessons: number;
    modules: Record<string, CourseUserModuleScore>;
    exam: {
        is_certified: boolean;
        sections: Record<string, CourseUserModuleScore>;
    } | null;
};

export type ExamStatus =
    'locked' | 'not_started' | 'in_progress' | 'failed' | 'passed';

export type ExamTagBreakdown = Record<
    string,
    { correct: number; total: number }
>;

export type ExamAttemptSummary = {
    id: number;
    score_pct: number;
    passed: boolean;
    tag_breakdown: ExamTagBreakdown | null;
    submitted_at: string;
};

export type ExamSectionSummary = {
    key: string;
    title: string;
    pass_pct: number;
    question_count: number;
    attempts_used: number;
    attempts_allowed: number;
    is_passed: boolean;
    is_out_of_attempts: boolean;
    has_open_attempt: boolean;
    can_start: boolean;
    best_score: number | null;
    attempts: ExamAttemptSummary[];
};

export type ExamSummary = {
    title: string;
    status: ExamStatus;
    is_unlocked: boolean;
    is_passed: boolean;
    sections: ExamSectionSummary[];
};
