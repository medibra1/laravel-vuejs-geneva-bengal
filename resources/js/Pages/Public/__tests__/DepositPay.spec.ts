import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

// vi.mock(...) calls below are hoisted above this whole module, so their
// factories can't close over plain top-level `const`s (still in their TDZ
// at that point) — vi.hoisted() is the mocked-var equivalent that
// survives the hoist. Everything the two mocks below need is declared in
// one block since `mockStripe`'s chain depends on `mockPaymentElement`.
const { routerVisit, mockPaymentElement, elementsCreate, elementsSubmit, stripeElements, confirmPayment, loadStripe, getReadyCallback, resetReadyCallback } = vi.hoisted(() => {
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
    // Required by Stripe's deferred-mode flow — see submitPayment()'s own
    // call to elements.submit() before confirmPayment(). Defaults to no
    // error; individual tests override via elementsSubmit.mockResolvedValue(...).
    // Typed explicitly (not inferred from the default return value alone)
    // so a later .mockResolvedValue({ error: { message: '...' } }) type-checks.
    const elementsSubmit = vi.fn<() => Promise<{ error: { message: string } | undefined }>>(() => Promise.resolve({ error: undefined }));
    const stripeElements = vi.fn(() => ({ create: elementsCreate, submit: elementsSubmit }));
    const confirmPayment = vi.fn();
    const mockStripe = { elements: stripeElements, confirmPayment };

    return {
        routerVisit: vi.fn(),
        mockPaymentElement,
        elementsCreate,
        elementsSubmit,
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
        // Mocking it away here is the actual fix.
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
            cat_unavailable: "Ce chaton vient d'être réservé par quelqu'un d'autre. Aucun paiement n'a été effectué.",
            back_to_cat_link: 'Retour aux chatons disponibles',
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function jsonResponse(body: unknown, ok = true): Response {
    return { ok, json: () => Promise.resolve(body) } as Response;
}

function mountDepositPay(overrides: Partial<{ catId: number | null; catSlug: string | null }> = {}) {
    return mount(DepositPay, {
        global: {
            plugins: [i18n],
            stubs: {
                PublicLayout: { template: '<div><slot /></div>' },
                // Head is mocked at the module level above, not stubbed
                // here — see the vi.mock('@inertiajs/vue3', ...) comment.
            },
        },
        props: {
            catId: overrides.catId === undefined ? 42 : overrides.catId,
            catName: 'Simba',
            catSlug: overrides.catSlug === undefined ? 'simba' : overrides.catSlug,
            amount: 50000,
            currency: 'CHF',
            stripePublishableKey: 'pk_test_123',
            name: 'Marie Dupont',
            email: 'marie@example.com',
            phone: '+41 79 000 00 00',
        },
    });
}

async function mountReady(overrides: Partial<{ catId: number | null; catSlug: string | null }> = {}) {
    const wrapper = mountDepositPay(overrides);
    await flushPromises();
    getReadyCallback()?.();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    elementsSubmit.mockResolvedValue({ error: undefined });
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
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ paymentIntentId: 'pi_test_123', clientSecret: 'pi_test_secret' }));
});

describe('DepositPay', () => {
    it('mounts a deferred Stripe Payment Element (no clientSecret) on load — no PaymentIntent is created yet', async () => {
        mountDepositPay();
        await flushPromises();

        expect(loadStripe).toHaveBeenCalledWith('pk_test_123');
        expect(stripeElements).toHaveBeenCalledWith(
            expect.objectContaining({ mode: 'payment', amount: 50000, currency: 'chf', paymentMethodTypes: ['card', 'twint'] }),
        );
        expect(elementsCreate).toHaveBeenCalledWith('payment');
        expect(mockPaymentElement.mount).toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
    });

    it('calls elements.submit() before confirm-intent and confirmPayment with the returned client secret when Payer is clicked', async () => {
        confirmPayment.mockResolvedValue({ paymentIntent: { id: 'pi_test', status: 'requires_capture' } });
        const wrapper = await mountReady();

        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        // Required by Stripe's deferred-mode integration — confirmPayment()
        // throws synchronously if elements.submit() was never called first.
        expect(elementsSubmit).toHaveBeenCalled();
        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('deposits.confirm-intent'),
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    cat_id: 42,
                    name: 'Marie Dupont',
                    email: 'marie@example.com',
                    phone: '+41 79 000 00 00',
                }),
            }),
        );
        expect(confirmPayment).toHaveBeenCalledWith({
            elements: expect.anything(),
            clientSecret: 'pi_test_secret',
            confirmParams: { return_url: expect.stringContaining('deposits.return') },
            redirect: 'if_required',
        });
    });

    it('shows an inline error and never calls confirm-intent or confirmPayment when elements.submit() itself fails', async () => {
        elementsSubmit.mockResolvedValue({ error: { message: 'Champs de carte invalides.' } });
        const wrapper = await mountReady();

        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Champs de carte invalides.');
        expect(fetch).not.toHaveBeenCalled();
        expect(confirmPayment).not.toHaveBeenCalled();
    });

    it('shows the cat-unavailable message and never calls Stripe when confirm-intent is rejected', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ message: 'Ce chaton a déjà été réservé.' }, false));
        const wrapper = await mountReady();

        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain("Ce chaton vient d'être réservé par quelqu'un d'autre");
        expect(confirmPayment).not.toHaveBeenCalled();
        expect(routerVisit).not.toHaveBeenCalled();
    });

    it('shows the Stripe error message inline, without navigating away, when the payment is declined', async () => {
        confirmPayment.mockResolvedValue({ error: { message: 'Votre carte a été refusée.' } });
        const wrapper = await mountReady();

        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Votre carte a été refusée.');
        expect(routerVisit).not.toHaveBeenCalled();
        // The form stays usable — not stuck on a disabled/processing button.
        expect(wrapper.find('button[type="button"]').attributes('disabled')).toBeUndefined();
    });

    it('redirects to deposits.return once confirmPayment resolves without needing a browser redirect', async () => {
        confirmPayment.mockResolvedValue({ paymentIntent: { id: 'pi_test', status: 'requires_capture' } });
        const wrapper = await mountReady();

        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(expect.stringContaining('deposits.return'));
    });

    it('keeps the Payment Element mounted and reusable when the payment is declined', async () => {
        confirmPayment.mockResolvedValue({ error: { message: 'Votre carte a été refusée.' } });
        const wrapper = await mountReady();
        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        expect(mockPaymentElement.destroy).not.toHaveBeenCalled();
    });

    describe('cancelling', () => {
        it('is a plain link back to the cat page — no server call involved', async () => {
            const wrapper = await mountReady();

            const link = wrapper.findAll('a').find((a) => a.text().includes('Annuler'));

            expect(link?.attributes('href')).toContain('cats.show');
            expect(fetch).not.toHaveBeenCalled();
        });

        it('links to the general cats list for a waiting-list checkout', async () => {
            const wrapper = await mountReady({ catId: null, catSlug: null });

            const link = wrapper.findAll('a').find((a) => a.text().includes('Annuler'));

            expect(link?.attributes('href')).toContain('cats.index');
        });
    });
});
