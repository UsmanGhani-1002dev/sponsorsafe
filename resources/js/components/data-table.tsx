import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/field';
import { cn } from '@/lib/cn';
import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

export interface Page<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    currentPage: number;
    lastPage: number;
}

export interface TableState {
    q: string;
    sort: string;
    dir: 'asc' | 'desc';
    filters: Record<string, string | null>;
    from?: string | null;
    to?: string | null;
}

/** The current table state as a query string, e.g. for export links that follow the same filters. */
export function tableQuery(state: TableState): string {
    const params = new URLSearchParams();
    const all: Record<string, string | null | undefined> = { q: state.q, sort: state.sort, dir: state.dir, from: state.from, to: state.to, ...state.filters };
    for (const [k, v] of Object.entries(all)) if (v) params.set(k, v);
    return params.toString();
}

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    className?: string;
    render: (row: T) => ReactNode;
}

export interface Filter {
    name: string;
    label: string;
    options: { value: string; label: string }[];
    /** The value the server uses when the filter is not in the URL. */
    fallback?: string;
    allLabel?: string;
}

/**
 * Shared table: sorting, search, filters and pages all happen on the server.
 * Each change is an Inertia partial reload of just this table's props, so the page never re-renders from scratch.
 */
export function DataTable<T extends { id: number | string }>({
    url,
    only,
    page,
    state,
    columns,
    filters = [],
    searchLabel = 'Search',
    dateRange = false,
    toolbar,
    empty,
}: {
    url: string;
    only: string[];
    page: Page<T>;
    state: TableState;
    columns: Column<T>[];
    filters?: Filter[];
    searchLabel?: string;
    /** Show "From" and "To" date filters (the server receives ?from=&to=). */
    dateRange?: boolean;
    /** Extra buttons on the right of the toolbar, e.g. exports. */
    toolbar?: ReactNode;
    empty: ReactNode;
}) {
    const [q, setQ] = useState(state.q);
    const first = useRef(true);

    const visit = (changes: Record<string, string | number | null>) => {
        const params: Record<string, string | number> = {};
        const merged: Record<string, string | number | null | undefined> = { q: state.q, sort: state.sort, dir: state.dir, from: state.from, to: state.to, ...state.filters, ...changes };
        for (const [k, v] of Object.entries(merged)) if (v !== null && v !== undefined && v !== '') params[k] = v;
        router.get(url, params, { only, preserveState: true, preserveScroll: true, replace: true });
    };

    // Search as you type, after a short pause.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => visit({ q, page: null }), 300);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const sortBy = (key: string) => visit({ sort: key, dir: state.sort === key && state.dir === 'asc' ? 'desc' : 'asc', page: null });

    return (
        <Card className="overflow-hidden">
            <div className="flex flex-wrap items-end gap-3 border-b border-line p-4">
                <label className="relative min-w-[220px] flex-1">
                    <span className="sr-only">{searchLabel}</span>
                    <Search size={16} aria-hidden className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={searchLabel}
                        className="min-h-11 w-full rounded-lg border border-line-strong bg-surface pr-3 pl-9 text-[15px] text-ink outline-none placeholder:text-muted focus:border-indigo-300 focus:ring-4 focus:ring-accent-ring"
                    />
                </label>
                {filters.map((f) => (
                    <label key={f.name} className="flex flex-col gap-1 text-[13px] font-medium text-ink-2">
                        {f.label}
                        <Select value={state.filters[f.name] ?? f.fallback ?? ''} onChange={(e) => visit({ [f.name]: e.target.value || null, page: null })} className="min-w-[180px]">
                            {!f.fallback && <option value="">{f.allLabel ?? 'All'}</option>}
                            {f.options.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </label>
                ))}
                {dateRange &&
                    (['from', 'to'] as const).map((k) => (
                        <label key={k} className="flex flex-col gap-1 text-[13px] font-medium text-ink-2">
                            {k === 'from' ? 'From' : 'To'}
                            <input
                                type="date"
                                value={state[k] ?? ''}
                                onChange={(e) => visit({ [k]: e.target.value || null, page: null })}
                                className="min-h-11 rounded-lg border border-line-strong bg-surface px-3 text-[15px] text-ink outline-none focus:border-indigo-300 focus:ring-4 focus:ring-accent-ring"
                            />
                        </label>
                    ))}
                {toolbar && <div className="ml-auto flex flex-wrap items-center gap-2">{toolbar}</div>}
            </div>

            {page.data.length === 0 ? (
                empty
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                            <tr>
                                {columns.map((c) => (
                                    <th key={c.key} scope="col" aria-sort={state.sort === c.key ? (state.dir === 'asc' ? 'ascending' : 'descending') : undefined} className={cn('px-5 py-3 whitespace-nowrap', c.className)}>
                                        {c.sortable ? (
                                            <button type="button" onClick={() => sortBy(c.key)} className="-mx-1 inline-flex min-h-8 items-center gap-1 rounded px-1 hover:text-ink">
                                                {c.label}
                                                {state.sort === c.key && (state.dir === 'asc' ? <ArrowUp size={14} aria-hidden /> : <ArrowDown size={14} aria-hidden />)}
                                            </button>
                                        ) : (
                                            c.label
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((row) => (
                                <tr key={row.id} className="border-t border-line hover:bg-canvas/60">
                                    {columns.map((c) => (
                                        <td key={c.key} className={cn('px-5 py-3.5 align-middle', c.className)}>
                                            {c.render(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {page.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm text-ink-2">
                    <span>
                        Showing {page.from}–{page.to} of {page.total}
                    </span>
                    {page.lastPage > 1 && (
                        <div className="flex gap-2">
                            <PageButton label="Previous page" disabled={page.currentPage <= 1} onClick={() => visit({ page: page.currentPage - 1 })}>
                                <ChevronLeft size={16} aria-hidden /> Previous
                            </PageButton>
                            <PageButton label="Next page" disabled={page.currentPage >= page.lastPage} onClick={() => visit({ page: page.currentPage + 1 })}>
                                Next <ChevronRight size={16} aria-hidden />
                            </PageButton>
                        </div>
                    )}
                </div>
            )}
        </Card>
    );
}

function PageButton({ label, disabled, onClick, children }: { label: string; disabled: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button type="button" aria-label={label} disabled={disabled} onClick={onClick} className="inline-flex min-h-10 items-center gap-1 rounded-lg border border-line-strong bg-surface px-3 font-semibold hover:bg-canvas disabled:opacity-50">
            {children}
        </button>
    );
}
