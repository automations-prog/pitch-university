import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import { CampaignForm } from '@/components/admin/campaign-form';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/admin/campaigns';
import type { Campaign } from '@/types';

export default function EditCampaign({ campaign }: { campaign: Campaign }) {
    return (
        <>
            <Head title={`Edit ${campaign.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={campaignsIndex()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Back to campaigns
                    </Link>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Edit campaign
                    </h2>
                </div>

                <CampaignForm
                    form={CampaignController.update.form(campaign.id)}
                    campaign={campaign}
                    submitLabel="Save changes"
                    submitIcon={<Check />}
                />
            </div>
        </>
    );
}

EditCampaign.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Campaigns', href: campaignsIndex() },
        { title: 'Edit campaign', href: campaignsIndex() },
    ],
};
