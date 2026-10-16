import { Search, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { resourceInputClass } from '@/lib/brand-theme';

/**
 * A search box that applies its value on submit and clears it with ×.
 */
export function SearchFilter({
    value,
    placeholder,
    onSearch,
}: {
    value: string | undefined;
    placeholder: string;
    onSearch: (search: string) => void;
}) {
    const [search, setSearch] = useState(value ?? '');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        onSearch(search.trim());
    }

    function clear() {
        setSearch('');

        if (value) {
            onSearch('');
        }
    }

    return (
        <form onSubmit={submit} className="grid flex-1 gap-1.5 sm:min-w-56">
            <Label
                htmlFor="search"
                className="text-muted-foreground text-xs font-semibold uppercase"
            >
                Search
            </Label>
            <div className="relative">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    id="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={placeholder}
                    className={`pl-9 ${search ? 'pr-9' : ''} ${resourceInputClass}`}
                />
                {search && (
                    <button
                        type="button"
                        onClick={clear}
                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                    >
                        <X className="size-4" />
                        <span className="sr-only">Clear search</span>
                    </button>
                )}
            </div>
        </form>
    );
}
