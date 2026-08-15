import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { ComponentCustomProperties } from 'vue';
import { createI18n } from 'vue-i18n';

// vi.mock(...) below is hoisted above this whole module, so the factory
// can't close over a plain top-level `const` (still in its TDZ at that
// point) — vi.hoisted() is the mocked-var equivalent that survives the
// hoist.
const { routerReload } = vi.hoisted(() => ({ routerReload: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        router: { reload: routerReload },
        // Real Head has no `name` (see @inertiajs/vue3/src/head.ts), so
        // Vue Test Utils' `stubs: { Head: true }` can never match it by
        // name — it renders for real and crashes reading
        // `this.$headManager`, only ever set up by createInertiaApp().
        // Mocking it away here is the actual fix; `Link` does have a
        // `name` so its `stubs: { Link: true }` entry works as intended.
        Head: { template: '<div><slot /></div>' },
    };
});

import DepositReturn from '../DepositReturn.vue';

const messages = {
    fr: {
        depositReturn: {
            meta_title: 'Votre acompte — Geneva Bengal',
            paid_heading: 'Merci, votre acompte a bien été reçu !',
            paid_body: 'Un e-mail de confirmation vient de vous être envoyé à {email}.',
            cancelled_heading: 'Paiement non finalisé',
            cancelled_body: "Vous avez annulé le paiement, aucun montant n'a été débité.",
            unavailable_heading: "Ce chaton vient d'être réservé",
            unavailable_body: "Quelqu'un d'autre vient de finaliser sa réservation. Vous n'avez pas été débité(e).",
            pending_heading: 'Nous traitons votre paiement',
            pending_body: 'Cela ne devrait prendre que quelques instants.',
            timeout_heading: 'Cela prend plus de temps que prévu',
            timeout_body: 'La confirmation tarde à arriver.',
            back_link: 'Retour aux chatons disponibles',
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function mountReturn(depositStatus: string, email: string | null = 'marie@example.com') {
    return mount(DepositReturn, {
        global: {
            plugins: [i18n],
            // Unlike DepositPay.vue (calls route() only from <script
            // setup>, where a plain global works), this component's
            // template calls route('cats.index') directly, which compiles
            // to _ctx.route(...) — that only resolves through the
            // component's globalProperties chain, not globalThis, hence
            // this on top of the globalThis.route set in beforeEach().
            // The real shape (ComponentCustomProperties, augmented by
            // Inertia with $inertia/$page/etc.) isn't needed for this
            // test — only `route` is ever read from it.
            config: { globalProperties: { route: globalThis.route } as unknown as ComponentCustomProperties },
            stubs: {
                PublicLayout: { template: '<div><slot /></div>' },
                // Head is mocked at the module level above, not stubbed
                // here — see the vi.mock('@inertiajs/vue3', ...) comment.
                Link: true,
            },
        },
        props: { depositStatus, email },
    });
}

beforeEach(() => {
    vi.useRealTimers();
    routerReload.mockClear();
    // The "back to available kittens" link renders route('cats.index')
    // on every mount — needed even for tests that never assert on it.
    globalThis.route = vi.fn((name: string) => `https://geneva-bengal.test/fr/${name}`) as unknown as typeof route;
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

    it('names the address the confirmation email was sent to on the paid screen', () => {
        vi.useFakeTimers();
        const wrapper = mountReturn('paid', 'marie@example.com');

        expect(wrapper.text()).toContain('marie@example.com');
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

    it('stops polling and shows the timeout message after the maximum number of attempts even if the deposit never leaves pending', async () => {
        vi.useFakeTimers();
        const wrapper = mountReturn('pending');

        // Still showing the ordinary pending message before the cap.
        expect(wrapper.text()).toContain(messages.fr.depositReturn.pending_heading);
        expect(wrapper.text()).not.toContain(messages.fr.depositReturn.timeout_heading);

        // 20 attempts at 3.5s each, then one more tick for the interval to
        // notice the cap was reached (the check runs at the top of each
        // tick, so it's the 21st tick that sees pollAttempts >= 20 and
        // flips pollTimedOut).
        await vi.advanceTimersByTimeAsync(3500 * 21);
        expect(routerReload).toHaveBeenCalledTimes(20);

        expect(wrapper.text()).toContain(messages.fr.depositReturn.timeout_heading);
        expect(wrapper.text()).not.toContain(messages.fr.depositReturn.pending_heading);

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
