import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

// vi.mock(...) calls below are hoisted above this whole module, so their
// factories can't close over plain top-level `const`s (still in their TDZ
// at that point) — vi.hoisted() is the mocked-var equivalent that
// survives the hoist. Everything the two mocks below need is declared in
// one block since `mockStripe`'s chain depends on `mockPaymentElement`.
const { routerVisit, mockPaymentElement, elementsCreate, stripeElements, confirmPayment, loadStripe, getReadyCallback, resetReadyCallback } = vi.hoisted(() => {
    // Captured so the test can fire it itself, exactly like Stripe.js
    // would once the real iframe finishes loading — the Payer button
    // stays disabled until then (see DepositPay.vue's :disabled binding).
    let readyCallback: (() => void) | null = null;
    const mockPaymentElement = {
        mount: vi.fn(),
        destroy: vi.fn(),
        on: vi.fn((event: string, callback: () => void) => {
            if (event === 'ready') readyCallback = callback;
        }),
    };
    const elementsCreate = vi.fn(() => mockPaymentElement);
    const stripeElements = vi.fn(() => ({ create: elementsCreate }));
    const confirmPayment = vi.fn();
    const mockStripe = { elements: stripeElements, confirmPayment };

    return {
        routerVisit: vi.fn(),
        mockPaymentElement,
        elementsCreate,
        stripeElements,
        confirmPayment,
        loadStripe: vi.fn(() => Promise.resolve(mockStripe)),
        getReadyCallback: () => readyCallback,
        resetReadyCallback: () => {
            readyCallback = null;
        },
    };
});

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        router: { visit: routerVisit },
        // Real Head has no `name` (see @inertiajs/vue3/src/head.ts), so
        // Vue Test Utils' `stubs: { Head: true }` can never match it by
        // name — it renders for real and crashes reading
        // `this.$headManager`, only ever set up by createInertiaApp().
        // Mocking it away here is the actual fix; `Link` does have a
        // `name` so its `stubs: { Link: true }` entry works as intended.
        Head: { template: '<div><slot /></div>' },
    };
});

vi.mock('@stripe/stripe-js', () => ({ loadStripe }));

import DepositPay from '../DepositPay.vue';

const messages = {
    fr: {
        depositPay: {
            meta_title: 'Paiement de votre acompte — Geneva Bengal',
            heading: 'Paiement de votre acompte',
            summary_for_cat: 'Acompte de {amount} pour la réservation de {name}.',
            summary_waiting_list: "Acompte de {amount} pour votre inscription en liste d'attente.",
            loading: 'Chargement du formulaire de paiement…',
            load_error: "Le formulaire de paiement n'a pas pu se charger. Vérifiez votre connexion et rechargez la page.",
            generic_error: 'Une erreur est survenue pendant le paiement. Veuillez réessayer.',
            pay_button: 'Payer',
            paying_button: 'Paiement en cours…',
            cancel_link: 'Annuler et revenir en arrière',
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function mountDepositPay() {
    return mount(DepositPay, {
        global: {
            plugins: [i18n],
            stubs: {
                PublicLayout: { template: '<div><slot /></div>' },
                // Head is mocked at the module level above, not stubbed
                // here — see the vi.mock('@inertiajs/vue3', ...) comment.
                Link: true,
            },
        },
        props: {
            paymentIntentId: 'pi_test_123',
            clientSecret: 'pi_test_secret',
            stripePublishableKey: 'pk_test_123',
            catName: 'Simba',
            amount: 50000,
            currency: 'CHF',
        },
    });
}

async function mountReady() {
    const wrapper = mountDepositPay();
    await flushPromises();
    getReadyCallback()?.();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    resetReadyCallback();
    globalThis.route = vi.fn((name: string, params?: Record<string, unknown>) => {
        const query = params
            ? '?'
                  + Object.entries(params)
                      .map(([key, value]) => `${key}=${value}`)
                      .join('&')
            : '';

        return `https://geneva-bengal.test/fr/${name}${query}`;
    }) as unknown as typeof route;
});

describe('DepositPay', () => {
    it('mounts a Stripe Payment Element using the received client secret', async () => {
        mountDepositPay();
        await flushPromises();

        expect(loadStripe).toHaveBeenCalledWith('pk_test_123');
        expect(stripeElements).toHaveBeenCalledWith(expect.objectContaining({ clientSecret: 'pi_test_secret' }));
        expect(elementsCreate).toHaveBeenCalledWith('payment');
        expect(mockPaymentElement.mount).toHaveBeenCalled();
    });

    it('calls confirmPayment with redirect: if_required and a deposits.return url when Payer is clicked', async () => {
        confirmPayment.mockResolvedValue({ paymentIntent: { id: 'pi_test', status: 'requires_capture' } });
        const wrapper = await mountReady();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(confirmPayment).toHaveBeenCalledWith({
            elements: expect.anything(),
            confirmParams: { return_url: expect.stringContaining('deposits.return') },
            redirect: 'if_required',
        });
    });

    it('shows the Stripe error message inline, without navigating away, when the payment is declined', async () => {
        confirmPayment.mockResolvedValue({ error: { message: 'Votre carte a été refusée.' } });
        const wrapper = await mountReady();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Votre carte a été refusée.');
        expect(routerVisit).not.toHaveBeenCalled();
        // The form stays usable — not stuck on a disabled/processing button.
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });

    it('redirects to deposits.return once confirmPayment resolves without needing a browser redirect', async () => {
        confirmPayment.mockResolvedValue({ paymentIntent: { id: 'pi_test', status: 'requires_capture' } });
        const wrapper = await mountReady();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(expect.stringContaining('deposits.return'));
    });
});
