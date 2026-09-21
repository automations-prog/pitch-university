import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Copy,
    FileText,
    Mail,
    Mic,
    Phone,
    PhoneOff,
    Save,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInitials } from '@/hooks/use-initials';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { update as updateCallLog } from '@/routes/admin/call-logs';
import { index as screeningIndex } from '@/routes/admin/screening';
import type { CallRating, ScreeningResponse } from '@/types';

type Ratings = {
    clarity: CallRating | null;
    energy_tone: CallRating | null;
    composure_on_pushback: CallRating | null;
    overall_gut_check: CallRating | null;
};

const SCORECARD_FIELDS: {
    key: keyof Ratings;
    label: string;
    hint: string;
}[] = [
    {
        key: 'clarity',
        label: 'Clarity',
        hint: 'Can you understand them easily? Any major mumbling or pace issues?',
    },
    {
        key: 'energy_tone',
        label: 'Energy/Tone',
        hint: 'Do they sound friendly and engaged, or flat/disinterested?',
    },
    {
        key: 'composure_on_pushback',
        label: 'Composure on pushback',
        hint: 'When interrupted with "why should I keep talking to you," do they recover and keep talking, or go silent/flustered?',
    },
    {
        key: 'overall_gut_check',
        label: 'Overall gut check',
        hint: 'Would you feel comfortable putting this person on a real call?',
    },
];

const RATING_ITEM_CLASS: Record<CallRating, string> = {
    yes: 'data-[state=on]:border-emerald-500/50 data-[state=on]:bg-emerald-500/15 data-[state=on]:text-emerald-700 dark:data-[state=on]:text-emerald-400',
    somewhat:
        'data-[state=on]:border-amber-500/50 data-[state=on]:bg-amber-500/15 data-[state=on]:text-amber-700 dark:data-[state=on]:text-amber-400',
    no: 'data-[state=on]:border-red-500/50 data-[state=on]:bg-red-500/15 data-[state=on]:text-red-700 dark:data-[state=on]:text-red-400',
};

function ratingLabel(rating: CallRating): string {
    return rating.charAt(0).toUpperCase() + rating.slice(1);
}

const PLAYBACK_RATES = [0.5, 1, 1.25, 1.5, 2] as const;

export default function ScreeningResponseShow({
    response,
    ratingOptions,
}: {
    response: ScreeningResponse;
    ratingOptions: CallRating[];
}) {
    const [copiedText, copy] = useClipboard();
    const CopyIcon = copiedText === response.public_url ? Check : Copy;
    const getInitials = useInitials();

    const initialRatings: Ratings = {
        clarity: response.call_log?.clarity ?? null,
        energy_tone: response.call_log?.energy_tone ?? null,
        composure_on_pushback: response.call_log?.composure_on_pushback ?? null,
        overall_gut_check: response.call_log?.overall_gut_check ?? null,
    };

    const [notes, setNotes] = useState(response.call_log?.notes ?? '');
    const [ratings, setRatings] = useState<Ratings>(initialRatings);
    const [savingAssessment, setSavingAssessment] = useState(false);
    const [transcriptOpen, setTranscriptOpen] = useState(false);
    const [recordingOpen, setRecordingOpen] = useState(false);
    const [playbackRate, setPlaybackRate] = useState<number>(1);
    const audioRef = useRef<HTMLAudioElement>(null);

    const assessmentDirty =
        notes !== (response.call_log?.notes ?? '') ||
        SCORECARD_FIELDS.some(
            ({ key }) => ratings[key] !== initialRatings[key],
        );
    const hasBeenCalled = Boolean(response.call_log?.called_at);
    const ratedCount = SCORECARD_FIELDS.filter(
        ({ key }) => ratings[key] !== null,
    ).length;

    function saveAssessment() {
        if (!response.call_log) {
            return;
        }

        setSavingAssessment(true);
        router.patch(
            updateCallLog.url(response.call_log.id),
            { notes, ...ratings },
            {
                preserveScroll: true,
                onFinish: () => setSavingAssessment(false),
            },
        );
    }

    return (
        <>
            <Head title={response.full_name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-4">
                    <Link
                        href={screeningIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm transition-colors"
                    >
                        <ArrowLeft className="size-4" />
                        Back to screening
                    </Link>

                    <div className="dark:border-sidebar-border flex flex-col gap-4 rounded-2xl border border-[#ece7f5] bg-gradient-to-br from-[#f8f4ff] to-white p-5 sm:flex-row sm:items-center sm:justify-between dark:from-transparent dark:to-transparent">
                        <div className="flex items-center gap-4">
                            <Avatar className="size-14 shadow-sm">
                                <AvatarFallback className="bg-[linear-gradient(135deg,#473364_0%,#5a4177_60%,#8a5fae_100%)] text-base font-semibold text-white">
                                    {getInitials(response.full_name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="space-y-1">
                                <h2 className="text-xl font-semibold tracking-tight">
                                    {response.full_name}
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Submitted {formatDate(response.created_at)}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className={
                                    hasBeenCalled
                                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                }
                            >
                                {hasBeenCalled ? (
                                    <CheckCircle2 />
                                ) : (
                                    <PhoneOff />
                                )}
                                {hasBeenCalled
                                    ? 'Call completed'
                                    : 'Not yet called'}
                            </Badge>
                            <Badge variant="outline">
                                {ratedCount} / {SCORECARD_FIELDS.length} rated
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Candidate details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="flex items-center gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f598ff]/15">
                                    <Mail className="size-4 text-[#7a3fa0] dark:text-[#f5b8ff]" />
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs font-semibold uppercase">
                                        Email
                                    </p>
                                    <p className="text-sm font-medium">
                                        {response.email}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f598ff]/15">
                                    <Phone className="size-4 text-[#7a3fa0] dark:text-[#f5b8ff]" />
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs font-semibold uppercase">
                                        Phone number
                                    </p>
                                    <p className="text-sm font-medium">
                                        {response.phone_number}
                                    </p>
                                </div>
                            </div>

                            <Separator />

                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Submitted via
                                </Label>
                                <div className="flex w-full items-stretch gap-2">
                                    <Input
                                        readOnly
                                        value={response.public_url}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            copy(response.public_url)
                                        }
                                    >
                                        <CopyIcon />
                                        <span className="sr-only">
                                            Copy link
                                        </span>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Call log</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="flex items-center justify-between gap-2 rounded-lg bg-[#f8f4ff] px-3 py-2 dark:bg-white/5">
                                <span className="text-muted-foreground text-xs font-semibold uppercase">
                                    Timestamp
                                </span>
                                <span className="text-sm font-medium">
                                    {response.call_log?.called_at
                                        ? formatDate(
                                              response.call_log.called_at,
                                          )
                                        : 'Not yet called'}
                                </span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        response.call_log?.transcript &&
                                        setTranscriptOpen(true)
                                    }
                                    disabled={!response.call_log?.transcript}
                                    className="text-muted-foreground hover:enabled:border-foreground/30 hover:enabled:text-foreground flex flex-col items-center gap-1.5 rounded-lg border border-dashed p-4 text-center transition disabled:cursor-not-allowed"
                                >
                                    <FileText className="size-5 opacity-60" />
                                    <p className="text-xs font-semibold uppercase">
                                        Transcript
                                    </p>
                                    <p className="line-clamp-2 text-xs">
                                        {response.call_log?.transcript
                                            ? 'Click to view full transcript'
                                            : 'Available once AI calls are enabled.'}
                                    </p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        response.call_log?.recording_url &&
                                        setRecordingOpen(true)
                                    }
                                    disabled={!response.call_log?.recording_url}
                                    className="text-muted-foreground hover:enabled:border-foreground/30 hover:enabled:text-foreground flex flex-col items-center gap-1.5 rounded-lg border border-dashed p-4 text-center transition disabled:cursor-not-allowed"
                                >
                                    <Mic className="size-5 opacity-60" />
                                    <p className="text-xs font-semibold uppercase">
                                        Recording
                                    </p>
                                    <p className="text-xs">
                                        {response.call_log?.recording_url
                                            ? 'Click to play recording'
                                            : 'No recording yet.'}
                                    </p>
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Manager / HR notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="notes"
                                    className="text-muted-foreground text-xs font-semibold uppercase"
                                >
                                    Notes
                                </Label>
                                <Textarea
                                    id="notes"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Add notes about this candidate's call…"
                                    rows={4}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Candidate scorecard</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                Rated
                            </Label>
                            <span className="text-muted-foreground text-xs">
                                {ratedCount}/{SCORECARD_FIELDS.length} rated
                            </span>
                        </div>
                        {SCORECARD_FIELDS.map(({ key, label, hint }) => (
                            <div
                                key={key}
                                className="dark:border-sidebar-border rounded-lg border border-[#ece7f5] p-3"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-medium">
                                        {label}
                                    </p>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        size="sm"
                                        value={ratings[key] ?? ''}
                                        onValueChange={(
                                            value: CallRating | '',
                                        ) =>
                                            setRatings((current) => ({
                                                ...current,
                                                [key]: value || null,
                                            }))
                                        }
                                    >
                                        {ratingOptions.map((option) => (
                                            <ToggleGroupItem
                                                key={option}
                                                value={option}
                                                className={
                                                    RATING_ITEM_CLASS[option]
                                                }
                                            >
                                                {ratingLabel(option)}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {hint}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="flex items-center gap-3">
                    <Button
                        type="button"
                        size="sm"
                        className="w-fit"
                        onClick={saveAssessment}
                        disabled={
                            savingAssessment ||
                            !response.call_log ||
                            !assessmentDirty
                        }
                    >
                        <Save />
                        {savingAssessment ? 'Saving…' : 'Save assessment'}
                    </Button>
                    {assessmentDirty && !savingAssessment && (
                        <span className="text-muted-foreground text-xs">
                            Unsaved changes
                        </span>
                    )}
                </div>
            </div>

            <Dialog open={transcriptOpen} onOpenChange={setTranscriptOpen}>
                <DialogContent className="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Call transcript</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm whitespace-pre-wrap">
                        {response.call_log?.transcript}
                    </p>
                </DialogContent>
            </Dialog>

            <Dialog open={recordingOpen} onOpenChange={setRecordingOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Call recording</DialogTitle>
                    </DialogHeader>
                    {response.call_log?.recording_url && (
                        <div className="grid gap-3">
                            <div className="flex items-center justify-between gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold uppercase">
                                    Speed
                                </Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    size="sm"
                                    value={String(playbackRate)}
                                    onValueChange={(value: string) => {
                                        if (!value) {
                                            return;
                                        }

                                        const rate = Number(value);
                                        setPlaybackRate(rate);

                                        if (audioRef.current) {
                                            audioRef.current.playbackRate =
                                                rate;
                                        }
                                    }}
                                >
                                    {PLAYBACK_RATES.map((rate) => (
                                        <ToggleGroupItem
                                            key={rate}
                                            value={String(rate)}
                                        >
                                            {rate}x
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </div>
                            {/* eslint-disable-next-line jsx-a11y/media-has-caption */}
                            <audio
                                ref={audioRef}
                                controls
                                autoPlay
                                src={response.call_log.recording_url}
                                className="w-full"
                                onLoadedMetadata={(event) => {
                                    event.currentTarget.playbackRate =
                                        playbackRate;
                                }}
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

ScreeningResponseShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Screening', href: screeningIndex() },
    ],
};
