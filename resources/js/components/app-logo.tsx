import { usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-violet-300">
                <GraduationCap className="size-4.5 text-[#1c1726]" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-bold text-sidebar-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
