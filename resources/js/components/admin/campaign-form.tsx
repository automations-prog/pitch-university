import { Form, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { brandButtonClass, resourceInputClass } from '@/lib/brand-theme';
import { index as campaignsIndex } from '@/routes/admin/campaigns';
import type { Campaign } from '@/types';

/**
 * The name and active flag shared by the campaign create and edit pages.
 */
export function CampaignForm({
    form,
    campaign,
    submitLabel,
    submitIcon,
}: {
    form: { action: string; method: 'get' | 'post' };
    campaign?: Campaign;
    submitLabel: string;
    submitIcon: ReactNode;
}) {
    const [isActive, setIsActive] = useState(campaign?.is_active ?? true);

    return (
        <Form {...form}>
            {({ processing, errors }) => (
                <Card>
                    <CardHeader>
                        <CardTitle>Campaign details</CardTitle>
                        <CardDescription>
                            Teams belong to a campaign; agents join a team.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid items-start gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoFocus
                                defaultValue={campaign?.name}
                                placeholder="e.g. Medicare AEP"
                                className={resourceInputClass}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <input
                                type="hidden"
                                name="is_active"
                                value={isActive ? '1' : '0'}
                            />
                            <label className="flex h-11 items-center gap-2 text-sm">
                                <Checkbox
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(checked === true)
                                    }
                                />
                                Active
                            </label>
                            <InputError message={errors.is_active} />
                        </div>
                    </CardContent>
                    <CardFooter className="justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={campaignsIndex()}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className={brandButtonClass}
                        >
                            {processing ? <Spinner /> : submitIcon}
                            {submitLabel}
                        </Button>
                    </CardFooter>
                </Card>
            )}
        </Form>
    );
}
