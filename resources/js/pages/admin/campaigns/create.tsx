import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import CampaignController from '@/actions/App/Http/Controllers/Admin/CampaignController';
import { CampaignForm } from '@/components/admin/campaign-form';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/admin/campaigns';

export default function CreateCampaign() {
    return (
        <>
            <Head title="New campaign" />

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
                        New campaign
                    </h2>
                </div>

                <CampaignForm
                    form={CampaignController.store.form()}
                    submitLabel="Create campaign"
                    submitIcon={<Plus />}
                />
            </div>
        </>
    );
}

CreateCampaign.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Campaigns', href: campaignsIndex() },
        { title: 'New campaign', href: CampaignController.create() },
    ],
};
