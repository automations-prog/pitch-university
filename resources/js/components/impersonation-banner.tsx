import { Form, usePage } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { leave } from '@/routes/impersonate';

export default function ImpersonationBanner() {
    const { auth } = usePage().props;

    if (!auth.impersonating) {
        return null;
    }

    return (
        <div className="mx-4 mt-4 flex items-center justify-between gap-3 rounded-lg border border-[#f598ff]/30 bg-[#f598ff]/10 px-4 py-2 text-sm text-[#473364] dark:border-[#f598ff]/20 dark:bg-[#f598ff]/10 dark:text-[#f5b8ff]">
            <span className="flex items-center gap-2 font-medium">
                <UserRoundCog className="size-4" />
                You&apos;re impersonating {auth.user.name}
            </span>
            <Form {...leave.form()}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                    >
                        Return to admin
                    </Button>
                )}
            </Form>
        </div>
    );
}
