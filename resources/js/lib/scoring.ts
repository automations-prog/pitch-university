export function scoreBadgeVariant(
    score: number | null,
): 'outline' | 'secondary' | 'destructive' {
    if (score === null) {
        return 'secondary';
    }

    if (score >= 80) {
        return 'outline';
    }

    if (score >= 60) {
        return 'secondary';
    }

    return 'destructive';
}
