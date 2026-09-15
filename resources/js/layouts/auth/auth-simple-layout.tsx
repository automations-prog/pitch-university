import { Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const LIGHT_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.25), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.35), transparent 50%), linear-gradient(160deg, #3a2a54 0%, #473364 45%, #5a4177 100%)';

const DARK_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.12), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.2), transparent 50%), linear-gradient(160deg, #14101f 0%, #1c1530 45%, #241a3d 100%)';

const DOT_GRID = 'radial-gradient(rgba(255,255,255,0.14) 1px, transparent 1px)';

// Small decorative shapes drifting around the card — mirrors welcome.tsx's
// PARTICLES, trimmed down to suit the narrower auth layout.
const PARTICLES = [
    {
        top: '10%',
        left: '8%',
        size: 9,
        kind: 'dot',
        dx: 16,
        dy: -20,
        duration: 9,
        delay: 0,
    },
    {
        top: '20%',
        left: '90%',
        size: 12,
        kind: 'ring',
        dx: -18,
        dy: 14,
        duration: 11,
        delay: 0.6,
    },
    {
        top: '80%',
        left: '12%',
        size: 8,
        kind: 'dot',
        dx: 14,
        dy: 18,
        duration: 8,
        delay: 1.2,
    },
    {
        top: '88%',
        left: '85%',
        size: 14,
        kind: 'ring',
        dx: -14,
        dy: -16,
        duration: 13,
        delay: 0.3,
    },
    {
        top: '50%',
        left: '95%',
        size: 6,
        kind: 'dot',
        dx: -10,
        dy: 20,
        duration: 7,
        delay: 1.8,
    },
] as const;

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh items-center justify-center overflow-hidden p-6 md:p-10">
            <style>{`
                @keyframes auth-drift {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(-3%, 4%) scale(1.08); }
                }
                @keyframes auth-drift-reverse {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(4%, -3%) scale(1.1); }
                }
                @keyframes auth-float-badge {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-6px); }
                }
                @keyframes auth-particle-float {
                    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.35; }
                    50% { transform: translate(var(--dx), var(--dy)) rotate(180deg); opacity: 0.9; }
                }
                @media (prefers-reduced-motion: reduce) {
                    [style*="animation"] { animation: none !important; }
                }
            `}</style>

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
                className="absolute top-[10%] left-[10%] h-56 w-56 rounded-full opacity-25 blur-3xl"
                style={{
                    background: 'linear-gradient(135deg, #f598ff, #5a4177)',
                    animation: 'auth-drift 14s ease-in-out infinite',
                }}
            />
            <div
                className="absolute right-[12%] bottom-[12%] h-64 w-64 rounded-full opacity-20 blur-3xl"
                style={{
                    background: 'linear-gradient(135deg, #8a5fae, #f598ff)',
                    animation: 'auth-drift-reverse 16s ease-in-out infinite',
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
                        animation: `auth-particle-float ${particle.duration}s ease-in-out infinite`,
                        animationDelay: `${particle.delay}s`,
                    }}
                />
            ))}

            <div className="relative w-full max-w-sm">
                <div className="relative overflow-hidden rounded-[20px] bg-white shadow-[0_25px_50px_-12px_rgba(0,0,0,0.4)] dark:bg-[#1f1a30]">
                    <div
                        className="absolute -top-16 -right-16 size-40 rounded-full opacity-15"
                        style={{
                            background:
                                'linear-gradient(135deg, #f598ff, #5a4177)',
                        }}
                    />

                    <div className="relative flex flex-col gap-8 px-9 pt-10 pb-9">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-4"
                            >
                                <div
                                    className="mb-1 flex size-12 items-center justify-center rounded-2xl shadow-[0_10px_20px_-5px_rgba(0,0,0,0.3)]"
                                    style={{
                                        background:
                                            'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                                        animation:
                                            'auth-float-badge 5s ease-in-out infinite',
                                    }}
                                >
                                    <GraduationCap className="size-6 text-white" />
                                </div>
                                <span
                                    className="bg-clip-text text-lg font-extrabold text-transparent"
                                    style={{
                                        backgroundImage:
                                            'linear-gradient(135deg, #473364, #8a5fae 60%, #f598ff)',
                                    }}
                                >
                                    Pitch University
                                </span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-extrabold text-[#1c1530] dark:text-[#f2eefb]">
                                    {title}
                                </h1>
                                <p className="text-center text-sm text-[#6b6478] dark:text-[#b7aec9]">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
