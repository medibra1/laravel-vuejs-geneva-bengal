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
            countdown_label: 'Temps restant pour finaliser le paiement : {time}',
            session_expired: 'Votre session de paiement a expiré. Merci de reprendre votre réservation.',
            back_to_cat_link: 'Retour aux chatons disponibles',
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function jsonResponse(body: unknown): Response {
    return { ok: true, json: () => Promise.resolve(body) } as Response;
}

function mountDepositPay(overrides: Partial<{ hardExpiresAt: string | null; catSlug: string | null }> = {}) {
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
            paymentIntentId: 'pi_test_123',
            clientSecret: 'pi_test_secret',
            stripePublishableKey: 'pk_test_123',
            catName: 'Simba',
            catSlug: overrides.catSlug ?? 'simba',
            amount: 50000,
            currency: 'CHF',
            hardExpiresAt: overrides.hardExpiresAt === undefined ? new Date(Date.now() + 15 * 60_000).toISOString() : overrides.hardExpiresAt,
        },
    });
}

async function mountReady(overrides: Partial<{ hardExpiresAt: string | null; catSlug: string | null }> = {}) {
    const wrapper = mountDepositPay(overrides);
    await flushPromises();
    getReadyCallback()?.();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
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
    globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
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

        await wrapper.find('button[type="button"]').trigger('click');
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

    describe('hard-expiry countdown', () => {
        it('shows a countdown counting down from hardExpiresAt, in mm:ss', async () => {
            vi.useFakeTimers();
            const wrapper = await mountReady({ hardExpiresAt: new Date(Date.now() + 125_000).toISOString() });

            expect(wrapper.text()).toContain('2:05');

            await vi.advanceTimersByTimeAsync(5_000);
            expect(wrapper.text()).toContain('2:00');

            vi.useRealTimers();
        });

        it('recomputes from the fixed hard_expires_at timestamp rather than decrementing in place, so it never drifts', async () => {
            vi.useFakeTimers();
            const wrapper = await mountReady({ hardExpiresAt: new Date(Date.now() + 60_000).toISOString() });

            // Simulates a backgrounded tab: a single big jump instead of
            // many 1s ticks. A naive decrement-by-one-per-tick
            // implementation would still show ~59s here; recomputing from
            // the fixed deadline shows the real remaining time.
            await vi.advanceTimersByTimeAsync(45_000);
            expect(wrapper.text()).toContain('0:15');

            vi.useRealTimers();
        });

        it('turns visually pressing under 2 minutes remaining', async () => {
            vi.useFakeTimers();
            const wrapper = await mountReady({ hardExpiresAt: new Date(Date.now() + 121_000).toISOString() });

            expect(wrapper.find('p.text-red-600').exists()).toBe(false);

            await vi.advanceTimersByTimeAsync(2_000);
            expect(wrapper.find('p.text-red-600').exists()).toBe(true);

            vi.useRealTimers();
        });

        it('shows no countdown at all for a waiting-list checkout (hardExpiresAt null)', async () => {
            vi.useFakeTimers();
            const wrapper = await mountReady({ hardExpiresAt: null });

            expect(wrapper.text()).not.toContain('Temps restant');

            vi.useRealTimers();
        });

        it('expires the session locally once the countdown reaches zero, even without a failed ping', async () => {
            vi.useFakeTimers();
            const wrapper = await mountReady({ hardExpiresAt: new Date(Date.now() + 3_000).toISOString() });

            await vi.advanceTimersByTimeAsync(3_000);

            expect(wrapper.text()).toContain('Votre session de paiement a expiré');
            expect(wrapper.find('button[type="button"]').exists()).toBe(false);
            // No ping should have had time to fire yet (first one is
            // scheduled 60s out) — the countdown alone caught this.
            expect(fetch).not.toHaveBeenCalled();

            vi.useRealTimers();
        });
    });

    describe('checkout hold ping', () => {
        it('pings deposits.hold.touch with the payment intent id every 60 seconds', async () => {
            vi.useFakeTimers();
            await mountReady();

            expect(fetch).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(60_000);
            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('deposits.hold.touch'),
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ payment_intent_id: 'pi_test_123' }),
                }),
            );

            await vi.advanceTimersByTimeAsync(60_000);
            expect(fetch).toHaveBeenCalledTimes(2);

            vi.useRealTimers();
        });

        it('expires the session when a ping reports the hold is gone (ok: false)', async () => {
            vi.useFakeTimers();
            globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ ok: false }));
            const wrapper = await mountReady();

            await vi.advanceTimersByTimeAsync(60_000);
            await flushPromises();

            expect(wrapper.text()).toContain('Votre session de paiement a expiré');
            expect(wrapper.find('button[type="button"]').exists()).toBe(false);

            vi.useRealTimers();
        });

        it('does not treat a network failure on the ping itself as an expired session', async () => {
            vi.useFakeTimers();
            globalThis.fetch = vi.fn().mockRejectedValue(new Error('network down'));
            const wrapper = await mountReady();

            await vi.advanceTimersByTimeAsync(60_000);
            await flushPromises();

            expect(wrapper.text()).not.toContain('Votre session de paiement a expiré');

            vi.useRealTimers();
        });

        it('stops pinging once the session has already expired locally via the countdown', async () => {
            vi.useFakeTimers();
            await mountReady({ hardExpiresAt: new Date(Date.now() + 3_000).toISOString() });

            await vi.advanceTimersByTimeAsync(3_000);
            expect(fetch).not.toHaveBeenCalled();

            // Would have fired a ping at 60s had it not already stopped.
            await vi.advanceTimersByTimeAsync(60_000);
            expect(fetch).not.toHaveBeenCalled();

            vi.useRealTimers();
        });
    });

    describe('cancelling', () => {
        it('releases the checkout hold and navigates to the cancelled return url when Cancel is clicked', async () => {
            const wrapper = await mountReady();

            const buttons = wrapper.findAll('button[type="button"]');
            const cancelButton = buttons[buttons.length - 1];
            await cancelButton.trigger('click');
            await flushPromises();

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('deposits.hold.release'),
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ payment_intent_id: 'pi_test_123' }),
                }),
            );
            expect(routerVisit).toHaveBeenCalledWith(expect.stringContaining('status=cancelled'));
        });
    });

    it('keeps the Payment Element mounted and reusable, without releasing the hold, when the payment is declined', async () => {
        vi.useFakeTimers();
        confirmPayment.mockResolvedValue({ error: { message: 'Votre carte a été refusée.' } });
        const wrapper = await mountReady();
        await wrapper.find('button[type="button"]').trigger('click');
        await flushPromises();

        // The decline itself must never call the release endpoint — only
        // the explicit Cancel button does (see CLAUDE.md: the same
        // visitor is still trying to pay).
        expect(fetch).not.toHaveBeenCalledWith(expect.stringContaining('deposits.hold.release'), expect.anything());
        expect(mockPaymentElement.destroy).not.toHaveBeenCalled();

        vi.useRealTimers();
    });
});
