import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    BAR_COLORS,
    ChartTooltip,
    EmptyChartState,
    hasValues,
    STATUS_COLORS,
} from '@/components/charts/chart-primitives';
import type { ChartDatum, DashboardCharts } from '@/types';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

function StatusDonut({ data }: { data: ChartDatum[] }) {
    if (!hasValues(data)) {
        return <EmptyChartState />;
    }

    return (
        <ResponsiveContainer width="100%" height={220}>
            <PieChart>
                <Pie
                    data={data}
                    dataKey="value"
                    nameKey="name"
                    innerRadius={55}
                    outerRadius={80}
                    paddingAngle={2}
                >
                    {data.map((entry, index) => (
                        <Cell
                            key={entry.name}
                            fill={STATUS_COLORS[index % STATUS_COLORS.length]}
                        />
                    ))}
                </Pie>
                <Tooltip content={<ChartTooltip />} />
            </PieChart>
        </ResponsiveContainer>
    );
}

function DistributionBarChart({ data }: { data: ChartDatum[] }) {
    if (!hasValues(data)) {
        return <EmptyChartState />;
    }

    return (
        <ResponsiveContainer width="100%" height={220}>
            <BarChart data={data}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis
                    dataKey="name"
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    interval={0}
                    angle={data.length > 4 ? -20 : 0}
                    textAnchor={data.length > 4 ? 'end' : 'middle'}
                    height={data.length > 4 ? 40 : 24}
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    width={28}
                />
                <Tooltip
                    content={<ChartTooltip />}
                    cursor={{ fill: '#f598ff', fillOpacity: 0.08 }}
                />
                <Bar dataKey="value" radius={[6, 6, 0, 0]}>
                    {data.map((entry, index) => (
                        <Cell
                            key={entry.name}
                            fill={BAR_COLORS[index % BAR_COLORS.length]}
                        />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

export default function DashboardCharts({
    charts,
}: {
    charts: DashboardCharts;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Agent status split</CardTitle>
                    <CardDescription>
                        Active vs. inactive agents in the current filters.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <StatusDonut data={charts.status_split} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Training completion</CardTitle>
                    <CardDescription>
                        Agents grouped by trainings completed.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DistributionBarChart data={charts.completion} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Average score distribution</CardTitle>
                    <CardDescription>
                        Agents grouped by average roleplay score band.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DistributionBarChart data={charts.score_bands} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Agents per license</CardTitle>
                    <CardDescription>
                        How many agents are assigned to each active license.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DistributionBarChart data={charts.per_license} />
                </CardContent>
            </Card>
        </div>
    );
}
