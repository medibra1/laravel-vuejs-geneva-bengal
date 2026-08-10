import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { useDebounceFn } from '@vueuse/core';
import type { DataTableSortEvent } from 'primevue/datatable';

export type TableFilterValue = string | number | null;

export interface UseTableQueryOptions<F extends Record<string, TableFilterValue>> {
    /** Ziggy route name the table lives on, e.g. 'admin.cats.adoption.index'. */
    routeName: string;
    /**
     * One entry per filter this table supports, each set to its "empty"
     * value (usually `null`) — also what defines which `filter[key]=`
     * query params are read back out of the URL on load, so every caller
     * gets state restored on refresh/shared link for free.
     */
    filterDefaults: F;
    /**
     * Route params outside the filter[...]/sort/page shape (e.g. Deposits'
     * `waiting_list` nav toggle) — passed through unchanged on every
     * request this composable makes.
     */
    extraParams?: Record<string, FormDataConvertible>;
    /**
     * Filter keys whose value should be read back from the URL as a
     * number rather than a string — e.g. an id filter bound to a PrimeVue
     * `Select`'s `option-value`, which needs `filters.color_id` to match
     * the type of the option list's `id` field. Can't be inferred from
     * `filterDefaults` alone since most defaults are `null`.
     */
    numericFilterKeys?: Array<keyof F & string>;
    /** Debounce window for `applyFiltersDebounced`, in ms. */
    debounceMs?: number;
}

/**
 * Generic filter/sort/search state for an admin list page backed by
 * spatie/laravel-query-builder (`filter[key]=value` + `sort=field`/`-field`
 * query params) — every request goes through `router.get(...)` with
 * `preserveState`/`preserveScroll`/`replace` so paging back and forth
 * through filtered views doesn't spam the browser history or reset scroll.
 *
 * Deliberately has no cats/deposits/owners-specific knowledge — a page
 * supplies its own `filterDefaults` shape and reads/writes `filters`
 * directly (e.g. in a PrimeVue `Select`'s `v-model`).
 */
export function useTableQuery<F extends Record<string, TableFilterValue>>(options: UseTableQueryOptions<F>) {
    const { routeName, filterDefaults, extraParams = {}, numericFilterKeys = [], debounceMs = 300 } = options;
    const numericKeys = new Set<string>(numericFilterKeys);

    const urlParams = new URLSearchParams(window.location.search);

    const filters = reactive({ ...filterDefaults }) as F;
    (Object.keys(filterDefaults) as Array<keyof F & string>).forEach((key) => {
        const raw = urlParams.get(`filter[${key}]`);
        if (raw === null || raw === '') return;

        filters[key] = (numericKeys.has(key) ? Number(raw) : raw) as F[typeof key];
    });

    const rawSort = urlParams.get('sort');
    const sort = reactive<{ field: string | null; order: 1 | -1 | null }>({
        field: rawSort ? rawSort.replace(/^-/, '') : null,
        order: rawSort ? (rawSort.startsWith('-') ? -1 : 1) : null,
    });

    function buildParams(page?: number): Record<string, FormDataConvertible> {
        const filterPayload: Record<string, FormDataConvertible> = {};
        (Object.keys(filters) as Array<keyof F & string>).forEach((key) => {
            const value = filters[key];
            if (value !== null && value !== '') filterPayload[key] = value;
        });

        return {
            ...extraParams,
            filter: filterPayload,
            sort: sort.field ? `${sort.order === -1 ? '-' : ''}${sort.field}` : undefined,
            page,
        };
    }

    function visit(page?: number): void {
        router.get(route(routeName), buildParams(page), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    /** Bind to Selects/date inputs — they don't fire per-keystroke, so no debounce needed. */
    function applyFilters(): void {
        visit();
    }

    /** Bind to free-text search inputs so typing doesn't fire a request per keystroke. */
    const applyFiltersDebounced = useDebounceFn(() => visit(), debounceMs);

    /**
     * Bind to PrimeVue DataTable's `@sort` event (requires `lazy` +
     * `sortable` columns). `event.sortField` can technically be a
     * `(item) => string` accessor per PrimeVue's typing — never happens
     * for our plain `field="..."` columns, so anything but a string is
     * treated as "no sort".
     */
    function onSort(event: DataTableSortEvent): void {
        sort.field = typeof event.sortField === 'string' ? event.sortField : null;
        sort.order = event.sortOrder === 1 || event.sortOrder === -1 ? event.sortOrder : null;
        visit();
    }

    function goToPage(page: number): void {
        visit(page);
    }

    return {
        filters,
        // `undefined`, not `null`, to match DataTable's own `sortField`/
        // `sortOrder` prop types (unset = undefined for those props).
        sortField: computed(() => sort.field ?? undefined),
        sortOrder: computed(() => sort.order ?? undefined),
        applyFilters,
        applyFiltersDebounced,
        onSort,
        goToPage,
    };
}
