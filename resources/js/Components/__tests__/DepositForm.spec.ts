import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';

// A plain reactive object stands in for Inertia's real useForm() return
// value — cheap to mutate directly from the test (form.errors = {...}),
// and DepositForm.vue only ever reads/writes plain properties on it, never
// calls methods like reset()/transform() that a real form object also has.
const mockForm = reactive({
    name: '',
    email: '',
    phone: '',
    cat_id: null as number | null,
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
});

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>('@inertiajs/vue3');

    return {
        ...actual,
        useForm: () => mockForm,
        usePage: () => ({
            props: {
                honeypot: {
                    enabled: false,
                    nameFieldName: 'hp_name',
                    validFromFieldName: 'hp_valid_from',
                    encryptedValidFrom: 'enc',
                },
            },
        }),
    };
});

import DepositForm from '../DepositForm.vue';

const messages = {
    fr: {
        deposit: {
            reserve_button: 'Réserver avec un acompte de {amount}',
            stripe_notice: 'Paiement sécurisé par carte ou TWINT via notre partenaire Stripe, sans quitter cette page.',
            label_full_name: 'Nom complet',
            label_email: 'E-mail',
            label_phone: 'Téléphone (facultatif)',
            continue_button: 'Continuer vers le paiement',
            cancel: 'Annuler',
            cat_unavailable_error: "Ce chaton vient d'être réservé, veuillez rafraîchir la page.",
        },
    },
};

const i18n = createI18n({ legacy: false, locale: 'fr', messages });

function mountForm() {
    return mount(DepositForm, {
        global: { plugins: [i18n] },
        props: { catId: 42, amountLabel: 'CHF 500.00' },
    });
}

beforeEach(() => {
    mockForm.errors = {};
    mockForm.processing = false;
    globalThis.route = vi.fn((name: string) => `/fr/${name}`) as unknown as typeof route;
});

describe('DepositForm', () => {
    it('shows a friendly translated message when the backend rejects cat_id as already reserved, without crashing', async () => {
        const wrapper = mountForm();
        await wrapper.find('button').trigger('click'); // opens the form

        // The real backend error (see CatIsAvailableForDeposit /
        // Public\DepositController::store()) is __()-translated per the
        // active locale — this is its French text (see lang/fr.json) — the
        // component must show its own vue-i18n copy instead of this raw
        // string. See CLAUDE.md.
        mockForm.errors = { cat_id: 'Ce chaton a déjà été réservé.' };
        await wrapper.vm.$nextTick();

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain("Ce chaton vient d'être réservé, veuillez rafraîchir la page.");
        expect(wrapper.text()).not.toContain('Ce chaton a déjà été réservé.');
    });

    it('shows no cat_id error when the form has none', async () => {
        const wrapper = mountForm();
        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).not.toContain("Ce chaton vient d'être réservé");
    });
});
