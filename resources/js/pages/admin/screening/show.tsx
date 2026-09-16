import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { resourceCardClass } from '@/lib/brand-theme';
import { dashboard } from '@/routes';
import { index as screeningIndex } from '@/routes/admin/screening';
import type { Screening, ScreeningResponse } from '@/types';

export default function ScreeningShow({
    screening,
    responses,
}: {
    screening: Screening;
    responses: ScreeningResponse[];
}) {
    const [copiedText, copy] = useClipboard();
    const CopyIcon = copiedText === screening.public_url ? Check : Copy;

    return (
        <>
            <Head title="Screening responses" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={screeningIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to screening
                    </Link>
                    <div className="space-y-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Screening
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Responses collected through this screening's public
                            link.
                        </p>
                    </div>
                </div>

                <div className="flex w-full max-w-lg items-stretch gap-2">
                    <Input readOnly value={screening.public_url} />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => copy(screening.public_url)}
                    >
                        <CopyIcon />
                        <span className="sr-only">Copy link</span>
                    </Button>
                </div>

                <div className={resourceCardClass}>
                    <Table className="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Full name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Birthday</TableHead>
                                <TableHead>Phone number</TableHead>
                                <TableHead>Submitted</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {responses.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-8 text-center"
                                    >
                                        No responses yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {responses.map((response) => (
                                <TableRow key={response.id}>
                                    <TableCell className="font-medium">
                                        {response.full_name}
                                    </TableCell>
                                    <TableCell>{response.email}</TableCell>
                                    <TableCell>
                                        {new Date(
                                            response.birthday,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        {response.phone_number}
                                    </TableCell>
                                    <TableCell>
                                        {new Date(
                                            response.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ScreeningShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Screening', href: screeningIndex() },
    ],
};
