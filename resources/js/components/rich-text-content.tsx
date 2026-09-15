import { richTextProseClass } from '@/lib/rich-text';
import { cn } from '@/lib/utils';

export function RichTextContent({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    return (
        <div
            className={cn('text-sm', richTextProseClass, className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
