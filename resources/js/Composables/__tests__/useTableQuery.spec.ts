import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerGet = vi.fn();

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        router: { get: routerGet },
    };
});

import { useTableQuery } from '../useTableQuery';

beforeEach(() => {
    routerGet.mockClear();
    globalThis.route = vi.fn((name: string) => `/${name}`) as unknown as typeof route;
    window.history.pushState({}, '', '/admin/cats/adoption');
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useTableQuery', () => {
    it('starts every filter at its default when the URL carries no filter[...] params', () => {
        const { filters } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null, type: null, color_id: null as number | null },
        });

        expect(filters).toEqual({ search: null, type: null, color_id: null });
    });

    it('hydrates filters from filter[...] query params already in the URL', () => {
        window.history.pushState({}, '', '/admin/cats/adoption?filter[search]=simba&filter[color_id]=3');

        const { filters } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null, color_id: null as number | null },
            numericFilterKeys: ['color_id'],
        });

        expect(filters.search).toBe('simba');
        expect(filters.color_id).toBe(3);
    });

    it('hydrates the current sort from a leading "-" in the URL', () => {
        window.history.pushState({}, '', '/admin/cats/adoption?sort=-price');

        const { sortField, sortOrder } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null },
        });

        expect(sortField.value).toBe('price');
        expect(sortOrder.value).toBe(-1);
    });

    it('applyFilters() visits the route with only the non-empty filters, preserving state/scroll', () => {
        const { filters, applyFilters } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null, color_id: null as number | null },
        });
        filters.search = 'simba';

        applyFilters();

        expect(routerGet).toHaveBeenCalledWith(
            '/admin.cats.adoption.index',
            { filter: { search: 'simba' }, sort: undefined, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('applyFiltersDebounced() collapses rapid calls into a single visit', async () => {
        vi.useFakeTimers();
        const { applyFiltersDebounced } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null },
        });

        applyFiltersDebounced();
        applyFiltersDebounced();
        applyFiltersDebounced();
        await vi.advanceTimersByTimeAsync(300);

        expect(routerGet).toHaveBeenCalledTimes(1);
    });

    it('onSort() records the field/direction and visits immediately', () => {
        const { onSort, sortField, sortOrder } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null },
        });

        onSort({ sortField: 'name', sortOrder: 1 });

        expect(sortField.value).toBe('name');
        expect(sortOrder.value).toBe(1);
        expect(routerGet).toHaveBeenCalledWith(
            '/admin.cats.adoption.index',
            { filter: {}, sort: 'name', page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('goToPage() includes the requested page alongside the current filters/sort', () => {
        const { goToPage } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null },
        });

        goToPage(3);

        expect(routerGet).toHaveBeenCalledWith(
            '/admin.cats.adoption.index',
            { filter: {}, sort: undefined, page: 3 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('passes extraParams through on every visit unchanged', () => {
        const { applyFilters } = useTableQuery({
            routeName: 'admin.cats.adoption.index',
            filterDefaults: { search: null as string | null },
            extraParams: { waiting_list: 1 },
        });

        applyFilters();

        expect(routerGet).toHaveBeenCalledWith(
            '/admin.cats.adoption.index',
            { waiting_list: 1, filter: {}, sort: undefined, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });
});
