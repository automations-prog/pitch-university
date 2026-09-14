import { usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div
                className="flex aspect-square size-8 items-center justify-center rounded-md shadow-[0_10px_20px_-5px_rgba(0,0,0,0.3)]"
                style={{
                    background:
                        'linear-gradient(135deg, #473364 0%, #5a4177 60%, #8a5fae 100%)',
                }}
            >
                <GraduationCap className="size-5 text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span
                    className="mb-0.5 truncate bg-clip-text leading-tight font-semibold text-transparent"
                    style={{
                        backgroundImage:
                            'linear-gradient(135deg, #473364, #8a5fae 60%, #f598ff)',
                    }}
                >
                    {name}
                </span>
            </div>
        </>
    );
}
