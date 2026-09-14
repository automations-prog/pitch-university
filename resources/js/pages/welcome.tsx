import { Head } from '@inertiajs/react';
import { BookOpen, GraduationCap, ShieldCheck } from 'lucide-react';

const LIGHT_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.25), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.35), transparent 50%), linear-gradient(160deg, #3a2a54 0%, #473364 45%, #5a4177 100%)';

const DARK_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.12), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.2), transparent 50%), linear-gradient(160deg, #14101f 0%, #1c1530 45%, #241a3d 100%)';

const DOT_GRID = 'radial-gradient(rgba(255,255,255,0.14) 1px, transparent 1px)';

const FEATURES = [
    {
        icon: BookOpen,
        title: 'Training',
        blurb: 'Courses and coursework built for the field.',
    },
    {
        icon: ShieldCheck,
        title: 'Licensing',
        blurb: 'Track every credential in one place.',
    },
    {
        icon: GraduationCap,
        title: 'Coaching',
        blurb: 'Live roleplay, scored on the spot.',
    },
];

// Small decorative shapes drifting around the hero — each moves along its
// own path (--dx/--dy), at its own size, speed, and delay, via CSS custom
// properties so one shared keyframe animation covers all of them.
const PARTICLES = [
    {
        top: '14%',
        left: '10%',
        size: 10,
        kind: 'dot',
        dx: 18,
        dy: -24,
        duration: 9,
        delay: 0,
    },
    {
        top: '22%',
        left: '82%',
        size: 14,
        kind: 'ring',
        dx: -22,
        dy: 16,
        duration: 11,
        delay: 0.6,
    },
    {
        top: '68%',
        left: '18%',
        size: 8,
        kind: 'dot',
        dx: 14,
        dy: 20,
        duration: 8,
        delay: 1.2,
    },
    {
        top: '78%',
        left: '72%',
        size: 18,
        kind: 'ring',
        dx: -16,
        dy: -18,
        duration: 13,
        delay: 0.3,
    },
    {
        top: '38%',
        left: '92%',
        size: 7,
        kind: 'dot',
        dx: -12,
        dy: 22,
        duration: 7,
        delay: 1.8,
    },
    {
        top: '85%',
        left: '46%',
        size: 9,
        kind: 'dot',
        dx: 20,
        dy: -14,
        duration: 10,
        delay: 0.9,
    },
    {
        top: '8%',
        left: '48%',
        size: 12,
        kind: 'ring',
        dx: 10,
        dy: 20,
        duration: 12,
        delay: 1.5,
    },
    {
        top: '52%',
        left: '6%',
        size: 6,
        kind: 'dot',
        dx: 16,
        dy: 16,
        duration: 8.5,
        delay: 0.4,
    },
] as const;

export default function Welcome() {
    return (
        <>
            <Head title="Pitch University" />

            <style>{`
                @keyframes drift {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(-3%, 4%) scale(1.08); }
                }
                @keyframes drift-reverse {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(4%, -3%) scale(1.1); }
                }
                @keyframes float-badge {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-6px); }
                }
                @keyframes pulse-dot {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.4; transform: scale(0.75); }
                }
                @keyframes particle-float {
                    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.35; }
                    50% { transform: translate(var(--dx), var(--dy)) rotate(180deg); opacity: 0.9; }
                }
                @media (prefers-reduced-motion: reduce) {
                    [style*="animation"] { animation: none !important; }
                }
            `}</style>

            <div className="relative flex min-h-svh items-center justify-center overflow-hidden p-6 md:p-10">
                <div
                    className="absolute inset-0 dark:hidden"
                    style={{ background: LIGHT_BACKGROUND }}
                />
                <div
                    className="absolute inset-0 hidden dark:block"
                    style={{ background: DARK_BACKGROUND }}
                />
                <div
                    className="absolute inset-0 opacity-[0.15]"
                    style={{
                        backgroundImage: DOT_GRID,
                        backgroundSize: '22px 22px',
                    }}
                />

                <div
                    className="absolute top-[8%] left-[8%] h-72 w-72 rounded-full opacity-25 blur-3xl"
                    style={{
                        background: 'linear-gradient(135deg, #f598ff, #5a4177)',
                        animation: 'drift 14s ease-in-out infinite',
                    }}
                />
                <div
                    className="absolute right-[10%] bottom-[10%] h-80 w-80 rounded-full opacity-20 blur-3xl"
                    style={{
                        background: 'linear-gradient(135deg, #8a5fae, #f598ff)',
                        animation: 'drift-reverse 16s ease-in-out infinite',
                    }}
                />

                {PARTICLES.map((particle, index) => (
                    <span
                        key={index}
                        aria-hidden="true"
                        className="pointer-events-none absolute"
                        style={{
                            top: particle.top,
                            left: particle.left,
                            width: particle.size,
                            height: particle.size,
                            borderRadius: '9999px',
                            background:
                                particle.kind === 'dot'
                                    ? 'rgba(245,152,255,0.8)'
                                    : 'transparent',
                            border:
                                particle.kind === 'ring'
                                    ? '1.5px solid rgba(245,152,255,0.6)'
                                    : undefined,
                            // @ts-expect-error custom properties read by the keyframe
                            '--dx': `${particle.dx}px`,
                            '--dy': `${particle.dy}px`,
                            animation: `particle-float ${particle.duration}s ease-in-out infinite`,
                            animationDelay: `${particle.delay}s`,
                        }}
                    />
                ))}

                <div className="relative z-10 flex w-full max-w-2xl flex-col items-center gap-9 text-center">
                    <div
                        className="flex size-20 items-center justify-center rounded-3xl shadow-[0_20px_50px_-15px_rgba(90,65,119,0.6)]"
                        style={{
                            background:
                                'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                            animation: 'float-badge 5s ease-in-out infinite',
                        }}
                    >
                        <GraduationCap className="size-10 text-white" />
                    </div>

                    <div className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 backdrop-blur-sm">
                        <span className="relative flex size-2">
                            <span
                                className="absolute inline-flex size-2 rounded-full bg-[#f598ff]"
                                style={{
                                    animation:
                                        'pulse-dot 1.8s ease-in-out infinite',
                                }}
                            />
                        </span>
                        <span className="text-xs font-semibold tracking-wide text-white uppercase">
                            Coming soon
                        </span>
                    </div>

                    <div className="space-y-4">
                        <h1
                            className="bg-clip-text text-5xl font-extrabold text-balance text-transparent sm:text-6xl"
                            style={{
                                backgroundImage:
                                    'linear-gradient(135deg, #ffffff 0%, #f5b8ff 55%, #c774ff 100%)',
                            }}
                        >
                            Pitch University
                        </h1>
                        <p className="mx-auto max-w-md text-base text-balance text-white/70">
                            Sales training, licensing, and enablement for our
                            agents — all in one place. We&apos;re putting the
                            finishing touches on it.
                        </p>
                    </div>

                    <div className="grid w-full grid-cols-1 gap-3 sm:grid-cols-3">
                        {FEATURES.map(({ icon: Icon, title, blurb }) => (
                            <div
                                key={title}
                                className="flex flex-col items-center gap-2 rounded-2xl border border-white/15 bg-white/[0.06] p-5 text-center backdrop-blur-sm transition-colors hover:bg-white/[0.1]"
                            >
                                <div className="flex size-10 items-center justify-center rounded-full bg-[#f598ff]/20 text-[#f5b8ff]">
                                    <Icon className="size-5" />
                                </div>
                                <span className="text-sm font-semibold text-white">
                                    {title}
                                </span>
                                <span className="text-xs text-white/60">
                                    {blurb}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
