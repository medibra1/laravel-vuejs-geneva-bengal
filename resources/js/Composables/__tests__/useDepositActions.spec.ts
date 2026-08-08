import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        router: { post: routerPost },
    };
});

import { formatAmount, formatDate, statusSeverity, useDepositActions } from '../useDepositActions';

beforeEach(() => {
    routerPost.mockClear();
    globalThis.route = vi.fn((name: string, params?: unknown) => `/${name}/${JSON.stringify(params ?? '')}`) as unknown as typeof route;
    vi.stubGlobal('confirm', vi.fn(() => true));
    Object.assign(navigator, { clipboard: { writeText: vi.fn().mockResolvedValue(undefined) } });
});

describe('formatAmount', () => {
    it('formats cents as a CHF amount', () => {
        expect(formatAmount(60000, 'CHF')).toContain('600');
    });
});

describe('formatDate', () => {
    it('returns an em dash for a null date', () => {
        expect(formatDate(null)).toBe('—');
    });
});

describe('statusSeverity', () => {
    it('maps known statuses to a PrimeVue severity', () => {
        expect(statusSeverity('paid')).toBe('success');
        expect(statusSeverity('pending')).toBe('warn');
        expect(statusSeverity('failed')).toBe('danger');
        expect(statusSeverity('refunded')).toBe('secondary');
    });
});

describe('useDepositActions', () => {
    it('posts to mark-paid when the confirm dialog is accepted', () => {
        const { markPaid } = useDepositActions();

        markPaid({ id: 7, payment_method: 'cash' }, 'Jeanne Dupont');

        expect(routerPost).toHaveBeenCalledWith('/admin.deposits.mark-paid/7', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('does not post when the confirm dialog is dismissed', () => {
        vi.stubGlobal('confirm', vi.fn(() => false));
        const { markPaid } = useDepositActions();

        markPaid({ id: 7, payment_method: 'cash' }, 'Jeanne Dupont');

        expect(routerPost).not.toHaveBeenCalled();
    });

    it('finalizes directly when the deposit already has an owner', () => {
        const { finalize, finalizeDialogVisible } = useDepositActions();

        finalize({ id: 9, owner_id: 3 }, 'Jeanne Dupont');

        expect(routerPost).toHaveBeenCalledWith('/admin.deposits.finalize/9', {}, expect.objectContaining({ preserveScroll: true }));
        expect(finalizeDialogVisible.value).toBe(false);
    });

    it('opens the owner dialog instead of posting when the deposit has no owner yet', () => {
        const { finalize, finalizeDialogVisible } = useDepositActions();

        finalize({ id: 9, owner_id: null }, 'Jeanne Dupont');

        expect(routerPost).not.toHaveBeenCalled();
        expect(finalizeDialogVisible.value).toBe(true);
    });

    it('copies the payment link and clears it again after the timeout', async () => {
        vi.useFakeTimers();
        const { copyPaymentLink, copiedId } = useDepositActions();

        await copyPaymentLink({ id: 5, payment_link_url: 'https://stripe.test/pay' });

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('https://stripe.test/pay');
        expect(copiedId.value).toBe(5);

        vi.advanceTimersByTime(2000);
        expect(copiedId.value).toBeNull();
        vi.useRealTimers();
    });
});
