import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL = 'all';

/**
 * A labelled filter dropdown with an "All …" option that clears it.
 */
export function FilterSelect({
    id,
    label,
    allLabel,
    value,
    options,
    onChange,
    className = 'w-full sm:w-44',
}: {
    id: string;
    label: string;
    allLabel: string;
    value: string | undefined;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    className?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label
                htmlFor={id}
                className="text-muted-foreground text-xs font-semibold uppercase"
            >
                {label}
            </Label>
            <Select
                value={value || ALL}
                onValueChange={(next) => onChange(next === ALL ? '' : next)}
            >
                <SelectTrigger id={id} className={className}>
                    <SelectValue placeholder={label} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{allLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
