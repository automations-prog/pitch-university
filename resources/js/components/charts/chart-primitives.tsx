import type { ChartDatum } from '@/types';

export const STATUS_COLORS = ['#8a5fae', '#e5e0f0'];
export const BAR_COLORS = ['#473364', '#5a4177', '#8a5fae', '#f598ff'];

export function ChartTooltip({
    active,
    payload,
    unit = 'agents',
}: {
    active?: boolean;
    payload?: { name: string; value: number }[];
    unit?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-popover text-popover-foreground rounded-md border px-3 py-1.5 text-xs shadow-md">
            <p className="font-medium">{payload[0].name}</p>
            <p className="text-muted-foreground">
                {payload[0].value} {unit}
            </p>
        </div>
    );
}

export function EmptyChartState({
    message = 'No data for the current filters.',
}: {
    message?: string;
}) {
    return (
        <div className="text-muted-foreground flex h-[220px] items-center justify-center text-sm">
            {message}
        </div>
    );
}

export function hasValues(data: ChartDatum[]): boolean {
    return data.some((datum) => datum.value > 0);
}
