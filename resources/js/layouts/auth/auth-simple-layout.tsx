import { Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const LIGHT_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.25), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.35), transparent 50%), linear-gradient(160deg, #3a2a54 0%, #473364 45%, #5a4177 100%)';

const DARK_BACKGROUND =
    'radial-gradient(circle at 15% 20%, rgba(245,152,255,0.12), transparent 45%), radial-gradient(circle at 85% 80%, rgba(139,97,199,0.2), transparent 50%), linear-gradient(160deg, #14101f 0%, #1c1530 45%, #241a3d 100%)';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh items-center justify-center overflow-hidden p-6 md:p-10">
            <div
                className="absolute inset-0 dark:hidden"
                style={{ background: LIGHT_BACKGROUND }}
            />
            <div
                className="absolute inset-0 hidden dark:block"
                style={{ background: DARK_BACKGROUND }}
            />

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
