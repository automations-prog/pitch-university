export function CourseProgressBar({
    value,
    max,
}: {
    value: number;
    max: number;
}) {
    const percentage = max > 0 ? Math.round((value / max) * 100) : 0;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
            className="bg-muted h-2 w-full overflow-hidden rounded-full"
        >
            <div
                className="h-full rounded-full bg-[linear-gradient(90deg,#c774ff_0%,#f598ff_100%)] transition-all"
                style={{ width: `${percentage}%` }}
            />
        </div>
    );
}
