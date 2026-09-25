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
} from '@/components/charts/chart-primitives';
import { trackStatusLabels } from '@/lib/scoring';
import type { AgentProgressSummary, AgentTrainingScore } from '@/types';
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

const COMPLETED_COLOR = BAR_COLORS[2];
const REMAINING_COLOR = '#e5e0f0';
const NOT_STARTED_COLOR = '#d8d3e6';

function CompletionDonut({ progress }: { progress: AgentProgressSummary }) {
    if (progress.total_trainings === 0) {
        return <EmptyChartState message="No training assigned yet." />;
    }

    const remaining = progress.total_trainings - progress.trainings_completed;
    const data = [
        { name: 'Completed', value: progress.trainings_completed },
        { name: 'Remaining', value: remaining },
    ];

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
                    <Cell fill={COMPLETED_COLOR} />
                    <Cell fill={REMAINING_COLOR} />
                </Pie>
                <Tooltip content={<ChartTooltip unit="tracks" />} />
            </PieChart>
        </ResponsiveContainer>
    );
}

function TrainingScoreTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: { payload: AgentTrainingScore }[];
}) {
    if (!active || !payload?.length) {
        return null;
    }

    const training = payload[0].payload;

    return (
        <div className="bg-popover text-popover-foreground rounded-md border px-3 py-1.5 text-xs shadow-md">
            <p className="font-medium">{training.name}</p>
            <p className="text-muted-foreground">
                {trackStatusLabels[training.status]}
                {training.average_score !== null &&
                    ` · Avg. ${training.average_score}%`}
            </p>
        </div>
    );
}

function TrainingScoreBarChart({
    trainingScores,
}: {
    trainingScores: AgentTrainingScore[];
}) {
    if (trainingScores.length === 0) {
        return <EmptyChartState message="No training assigned yet." />;
    }

    const data = trainingScores.map((training) => ({
        ...training,
        chartValue: training.average_score ?? 0,
    }));

    return (
        <ResponsiveContainer width="100%" height={240}>
            <BarChart data={data}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis
                    dataKey="name"
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    interval={0}
                    angle={trainingScores.length > 4 ? -20 : 0}
                    textAnchor={trainingScores.length > 4 ? 'end' : 'middle'}
                    height={trainingScores.length > 4 ? 40 : 24}
                />
                <YAxis
                    domain={[0, 100]}
                    tickLine={false}
                    axisLine={false}
                    fontSize={12}
                    width={28}
                />
                <Tooltip
                    content={<TrainingScoreTooltip />}
                    cursor={{ fill: '#f598ff', fillOpacity: 0.08 }}
                />
                <Bar dataKey="chartValue" radius={[6, 6, 0, 0]}>
                    {trainingScores.map((training) => (
                        <Cell
                            key={training.id}
                            fill={
                                training.average_score !== null
                                    ? COMPLETED_COLOR
                                    : NOT_STARTED_COLOR
                            }
                        />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

export default function AgentDashboardCharts({
    progress,
    trainingScores,
}: {
    progress: AgentProgressSummary;
    trainingScores: AgentTrainingScore[];
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Your completion</CardTitle>
                    <CardDescription>
                        Training tracks completed vs. remaining.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <CompletionDonut progress={progress} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Your scores per track</CardTitle>
                    <CardDescription>
                        Average of your best quiz score per module. Muted bars
                        are tracks with no quiz attempts yet.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <TrainingScoreBarChart trainingScores={trainingScores} />
                </CardContent>
            </Card>
        </div>
    );
}
