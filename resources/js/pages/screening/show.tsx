import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, GraduationCap } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as storeScreeningResponse } from '@/routes/screening';

const BACKGROUND =
    'radial-gradient(circle at 20% 20%, #7c3aed 0%, #4c1d78 35%, #2a1240 70%, #1a0b2e 100%)';

type Dot = {
    top: string;
    left?: string;
    right?: string;
    size: number;
    kind: 'dot' | 'ring';
    dx: number;
    dy: number;
    duration: number;
    delay: number;
};

const DOTS: Dot[] = [
    {
        top: '8%',
        left: '8%',
        size: 6,
        kind: 'dot',
        dx: 14,
        dy: -18,
        duration: 9,
        delay: 0,
    },
    {
        top: '70%',
        left: '12%',
        size: 5,
        kind: 'dot',
        dx: 12,
        dy: 16,
        duration: 8,
        delay: 1.1,
    },
    {
        top: '20%',
        right: '6%',
        size: 10,
        kind: 'ring',
        dx: -16,
        dy: 14,
        duration: 11,
        delay: 0.5,
    },
    {
        top: '50%',
        right: '8%',
        size: 5,
        kind: 'dot',
        dx: -10,
        dy: -14,
        duration: 7.5,
        delay: 1.7,
    },
    {
        top: '85%',
        right: '20%',
        size: 10,
        kind: 'ring',
        dx: -14,
        dy: -16,
        duration: 12,
        delay: 0.3,
    },
    {
        top: '35%',
        left: '20%',
        size: 4,
        kind: 'dot',
        dx: 10,
        dy: 12,
        duration: 6.5,
        delay: 0.8,
    },
    {
        top: '12%',
        left: '45%',
        size: 8,
        kind: 'ring',
        dx: -12,
        dy: 10,
        duration: 10,
        delay: 1.4,
    },
    {
        top: '92%',
        left: '40%',
        size: 5,
        kind: 'dot',
        dx: 16,
        dy: -10,
        duration: 9.5,
        delay: 0.6,
    },
    {
        top: '60%',
        right: '30%',
        size: 4,
        kind: 'dot',
        dx: -8,
        dy: 16,
        duration: 7,
        delay: 2,
    },
    {
        top: '5%',
        right: '35%',
        size: 6,
        kind: 'dot',
        dx: 10,
        dy: 14,
        duration: 8.5,
        delay: 1.9,
    },
];

type ScreeningFormData = {
    full_name: string;
    email: string;
    birthday: string;
    phone_number: string;
};

const FIELDS: {
    id: keyof ScreeningFormData;
    label: string;
    type: string;
    autoFocus?: boolean;
    minLength?: number;
    maxLength?: number;
    pattern?: string;
    hint?: string;
    max?: string;
}[] = [
    {
        id: 'full_name',
        label: 'Full name',
        type: 'text',
        autoFocus: true,
        minLength: 2,
        maxLength: 255,
        pattern: "(?=.*\\p{L})[\\p{L}\\p{M}'\\- ]+",
        hint: 'Letters, spaces, hyphens and apostrophes only',
    },
    {
        id: 'email',
        label: 'Email address',
        type: 'email',
        maxLength: 255,
    },
    {
        id: 'birthday',
        label: 'Birthday',
        type: 'date',
        max: new Date(Date.now() - 86400000).toISOString().slice(0, 10),
    },
    {
        id: 'phone_number',
        label: 'Phone number',
        type: 'tel',
        minLength: 7,
        maxLength: 20,
        pattern: '[0-9()+\\- ]+',
        hint: 'Digits, spaces, +, -, ( and ) only',
    },
];

const inputClass =
    'rounded-xl border-white/25 bg-white/8 text-white placeholder:text-white/50 focus-visible:border-white/60 focus-visible:bg-white/12 focus-visible:ring-0';

export default function PublicScreeningShow({ token }: { token: string }) {
    const { data, setData, post, processing, errors, wasSuccessful } =
        useForm<ScreeningFormData>({
            full_name: '',
            email: '',
            birthday: '',
            phone_number: '',
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post(storeScreeningResponse.url(token));
    }

    return (
        <div
            className="relative flex min-h-svh items-center justify-center overflow-hidden p-4"
            style={{ background: BACKGROUND, backgroundAttachment: 'fixed' }}
        >
            <Head title="Screening" />

            <style>{`
                @keyframes screening-drift {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(-3%, 4%) scale(1.08); }
                }
                @keyframes screening-drift-reverse {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(4%, -3%) scale(1.1); }
                }
                @keyframes screening-particle-float {
                    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.4; }
                    50% { transform: translate(var(--dx), var(--dy)) rotate(180deg); opacity: 1; }
                }
                @keyframes screening-spin {
                    from { transform: translate(-50%, -50%) rotate(0deg); }
                    to { transform: translate(-50%, -50%) rotate(360deg); }
                }
                @keyframes screening-pulse-glow {
                    0%, 100% { opacity: 0.2; transform: translate(-50%, -50%) scale(1); }
                    50% { opacity: 0.4; transform: translate(-50%, -50%) scale(1.06); }
                }
                @media (prefers-reduced-motion: reduce) {
                    [style*="animation"] { animation: none !important; }
                }
            `}</style>

            <div
                className="absolute top-1/2 left-1/2 h-[560px] w-[560px] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-30 blur-3xl"
                style={{
                    background:
                        'conic-gradient(from 0deg, #a855f7, #4c1d78, #7c3aed, #2a1240, #a855f7)',
                    animation: 'screening-spin 24s linear infinite',
                }}
            />
            <div
                className="absolute top-1/2 left-1/2 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#a855f7] blur-3xl"
                style={{
                    animation: 'screening-pulse-glow 6s ease-in-out infinite',
                }}
            />
            <div
                className="absolute top-[10%] left-[8%] h-72 w-72 rounded-full opacity-30 blur-3xl"
                style={{
                    background: 'linear-gradient(135deg, #a855f7, #4c1d78)',
                    animation: 'screening-drift 14s ease-in-out infinite',
                }}
            />
            <div
                className="absolute right-[10%] bottom-[10%] h-80 w-80 rounded-full opacity-25 blur-3xl"
                style={{
                    background: 'linear-gradient(135deg, #7c3aed, #2a1240)',
                    animation:
                        'screening-drift-reverse 16s ease-in-out infinite',
                }}
            />
            <div
                className="absolute top-[45%] left-[55%] h-64 w-64 rounded-full opacity-20 blur-3xl"
                style={{
                    background: 'linear-gradient(135deg, #f3e8ff, #7c3aed)',
                    animation: 'screening-drift 20s ease-in-out infinite',
                    animationDelay: '2s',
                }}
            />

            {DOTS.map((dot, index) => (
                <span
                    key={index}
                    aria-hidden="true"
                    className="pointer-events-none absolute rounded-full"
                    style={{
                        top: dot.top,
                        left: dot.left,
                        right: dot.right,
                        width: dot.size,
                        height: dot.size,
                        background:
                            dot.kind === 'dot'
                                ? 'rgba(255,255,255,0.35)'
                                : 'transparent',
                        border:
                            dot.kind === 'ring'
                                ? '1px solid rgba(255,255,255,0.4)'
                                : undefined,
                        // @ts-expect-error custom properties read by the keyframe
                        '--dx': `${dot.dx}px`,
                        '--dy': `${dot.dy}px`,
                        animation: `screening-particle-float ${dot.duration}s ease-in-out infinite`,
                        animationDelay: `${dot.delay}s`,
                    }}
                />
            ))}

            <div className="relative z-10 w-full max-w-[420px] rounded-3xl border border-white/[0.18] bg-white/[0.08] p-9 text-white shadow-[0_8px_32px_rgba(0,0,0,0.35)] backdrop-blur-xl">
                {wasSuccessful ? (
                    <div className="flex flex-col items-center gap-3 py-6 text-center">
                        <div className="relative flex size-16 items-center justify-center">
                            <span className="absolute inline-flex size-16 animate-ping rounded-full bg-emerald-400/20" />
                            <span className="absolute inline-flex size-12 animate-pulse rounded-full bg-emerald-400/20" />
                            <CheckCircle2 className="animate-in zoom-in relative size-12 text-emerald-400 duration-500" />
                        </div>
                        <h1 className="animate-in fade-in slide-in-from-bottom-2 fill-mode-both text-2xl font-bold duration-500">
                            Thank you!
                        </h1>
                        <p className="animate-in fade-in slide-in-from-bottom-2 fill-mode-both text-sm text-white/70 delay-100 duration-500">
                            Your response has been received. You may now close
                            this page.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="mb-5 flex size-14 items-center justify-center rounded-2xl border border-white/25 bg-white/15 text-2xl">
                            <GraduationCap className="size-7" />
                        </div>
                        <div className="mb-2 bg-gradient-to-r from-[#e9d5ff] to-[#f3e8ff] bg-clip-text text-xl font-bold text-transparent">
                            Pitch University
                        </div>
                        <h1 className="mt-2 mb-1.5 text-2xl font-bold">
                            Screening form
                        </h1>
                        <p className="mb-7 text-[15px] text-white/70">
                            Please fill out your details below.
                        </p>

                        <form onSubmit={submit} className="grid gap-5">
                            {FIELDS.map(
                                (
                                    {
                                        id,
                                        label,
                                        type,
                                        autoFocus,
                                        minLength,
                                        maxLength,
                                        pattern,
                                        hint,
                                        max,
                                    },
                                    index,
                                ) => (
                                    <div
                                        key={id}
                                        className="animate-in fade-in slide-in-from-bottom-3 fill-mode-both grid gap-2 duration-500"
                                        style={{
                                            animationDelay: `${index * 75}ms`,
                                        }}
                                    >
                                        <div className="flex items-baseline justify-between gap-2">
                                            <Label
                                                htmlFor={id}
                                                className="text-white/90"
                                            >
                                                {label}
                                            </Label>
                                            {hint && errors[id] && (
                                                <span className="rounded-md bg-[#140a1e]/90 px-2.5 py-1 text-xs text-white">
                                                    {hint}
                                                </span>
                                            )}
                                        </div>
                                        <Input
                                            id={id}
                                            type={type}
                                            required
                                            autoFocus={autoFocus}
                                            minLength={minLength}
                                            maxLength={maxLength}
                                            pattern={pattern}
                                            title={hint}
                                            max={max}
                                            value={data[id]}
                                            onChange={(e) =>
                                                setData(id, e.target.value)
                                            }
                                            className={`${inputClass} ${errors[id] ? 'border-red-400/80' : ''}`}
                                        />
                                        {!hint && errors[id] && (
                                            <span className="self-start text-xs text-red-300">
                                                {errors[id]}
                                            </span>
                                        )}
                                    </div>
                                ),
                            )}

                            <Button
                                type="submit"
                                disabled={processing}
                                className="animate-in fade-in slide-in-from-bottom-3 fill-mode-both mt-1 w-full rounded-2xl bg-gradient-to-r from-[#7c3aed] to-[#a855f7] py-3.5 text-base font-semibold text-white transition hover:brightness-110 active:scale-[0.98]"
                                style={{
                                    animationDelay: `${FIELDS.length * 75}ms`,
                                }}
                            >
                                {processing && <Spinner />}
                                Submit
                            </Button>
                        </form>
                    </>
                )}
            </div>
        </div>
    );
}
