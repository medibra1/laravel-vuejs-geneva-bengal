import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

const routerReload = vi.fn();

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        router: { reload: routerReload },
    };
});

import DepositReturn from '../DepositReturn.vue';

const messages = {
    fr: {
        depositReturn: {
            meta_title: 'Votre acompte — Geneva Bengal',
            paid_heading: 'Merci, votre acompte a bien été reçu !',
            paid_body: 'Un e-mail de confirmation vient de vous être envoyé.',
            cancelled_heading: 'Paiement non finalisé',
            cancelled_body: "Vous avez annulé le paiement, aucun montant n'a été débité.",
            unavailable_heading: "Ce chaton vient d'être réservé",
            unavailable_body: "Quelqu'un d'autre vient de finaliser sa réservation. Vous n'avez pas été débité(e).",
            pending_heading: 'Nous traitons votre paiement',
            pending_body: 'Cela ne devrait prendre que quelques instants.',
            back_link: 'Retour aux chatons disponibles',
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function mountReturn(depositStatus: string) {
    return mount(DepositReturn, {
        global: {
            plugins: [i18n],
            stubs: {
                PublicLayout: { template: '<div><slot /></div>' },
                Head: true,
                Link: true,
            },
        },
        props: { depositStatus },
    });
}

beforeEach(() => {
    vi.useRealTimers();
    routerReload.mockClear();
});

describe('DepositReturn polling', () => {
    it('polls with router.reload({ only: ["depositStatus"] }) while pending, and updates to paid once the reload reports it', async () => {
        vi.useFakeTimers();
        const wrapper = mountReturn('pending');

        expect(routerReload).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(3500);
        expect(routerReload).toHaveBeenCalledTimes(1);
        expect(routerReload).toHaveBeenCalledWith({ only: ['depositStatus'] });

        // Simulates what a real Inertia reload eventually does once the
        // webhook has captured the payment: the page's own depositStatus
        // prop comes back updated.
        await wrapper.setProps({ depositStatus: 'paid' });
        expect(wrapper.text()).toContain(messages.fr.depositReturn.paid_heading);

        // No further polling once the status left "pending".
        await vi.advanceTimersByTimeAsync(3500 * 5);
        expect(routerReload).toHaveBeenCalledTimes(1);

        vi.useRealTimers();
    });

    it('stops polling and shows the unavailable message when the reload reports the cat went to another payer', async () => {
        vi.useFakeTimers();
        const wrapper = mountReturn('pending');

        await vi.advanceTimersByTimeAsync(3500);
        expect(routerReload).toHaveBeenCalledTimes(1);

        await wrapper.setProps({ depositStatus: 'unavailable' });

        expect(wrapper.text()).toContain(messages.fr.depositReturn.unavailable_heading);
        expect(wrapper.text()).not.toContain(messages.fr.depositReturn.pending_heading);

        await vi.advanceTimersByTimeAsync(3500 * 5);
        expect(routerReload).toHaveBeenCalledTimes(1);

        vi.useRealTimers();
    });

    it('never shows the unavailable message for an ordinary still-pending or paid deposit', () => {
        // Fake timers even though nothing here is advanced — a 'pending'
        // mount starts a real setInterval() otherwise, which would keep
        // ticking into later tests since nothing here ever unmounts it.
        vi.useFakeTimers();
        const pendingWrapper = mountReturn('pending');
        expect(pendingWrapper.text()).not.toContain(messages.fr.depositReturn.unavailable_heading);

        const paidWrapper = mountReturn('paid');
        expect(paidWrapper.text()).not.toContain(messages.fr.depositReturn.unavailable_heading);
        vi.useRealTimers();
    });

    it('stops polling after the maximum number of attempts even if the deposit never leaves pending', async () => {
        vi.useFakeTimers();
        mountReturn('pending');

        // 20 attempts at 3.5s each.
        await vi.advanceTimersByTimeAsync(3500 * 20);
        expect(routerReload).toHaveBeenCalledTimes(20);

        // Capped — no further calls even much later.
        await vi.advanceTimersByTimeAsync(3500 * 5);
        expect(routerReload).toHaveBeenCalledTimes(20);

        vi.useRealTimers();
    });

    it('never starts polling for a deposit that is not pending', async () => {
        vi.useFakeTimers();
        mountReturn('paid');

        await vi.advanceTimersByTimeAsync(3500 * 3);
        expect(routerReload).not.toHaveBeenCalled();

        vi.useRealTimers();
    });
});
