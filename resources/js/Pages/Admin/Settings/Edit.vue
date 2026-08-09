<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import type { SiteSettings } from '@/types/models';

const props = defineProps<{
    settings: SiteSettings;
}>();

const form = useForm({
    social_facebook: props.settings.social_facebook ?? '',
    social_instagram: props.settings.social_instagram ?? '',
    social_youtube: props.settings.social_youtube ?? '',
    social_tiktok: props.settings.social_tiktok ?? '',
    address: props.settings.address ?? '',
    deposit_amount: props.settings.deposit_amount ?? null,
    price_range_min: props.settings.price_range_min ?? null,
    price_range_max: props.settings.price_range_max ?? null,
    default_seo_title: props.settings.default_seo_title ?? '',
    default_seo_description: props.settings.default_seo_description ?? '',
});

// form.* stays in centimes (what's stored and what admin.settings.update
// validates) — PrimeVue's InputNumber in currency mode reads/writes whole
// CHF, so it can't bind to those fields directly without silently
// truncating every amount to 1/100th of what was typed (100 CHF entered ->
// 100 saved -> "1.00 CHF" shown on the public site, which is exactly the
// bug this fixes).
function chfModel(key: 'deposit_amount' | 'price_range_min' | 'price_range_max') {
    return computed({
        get: () => (form[key] !== null ? form[key]! / 100 : null),
        set: (value: number | null) => {
            form[key] = value !== null ? Math.round(value * 100) : null;
        },
    });
}

const depositAmountChf = chfModel('deposit_amount');
const priceRangeMinChf = chfModel('price_range_min');
const priceRangeMaxChf = chfModel('price_range_max');

function submit(): void {
    form.put(route('admin.settings.update'));
}
</script>

<template>

    <Head title="Réglages du site" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">Réglages du site</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form class="space-y-8 bg-white dark:bg-neutral-800 p-6 shadow-sm sm:rounded-lg" @submit.prevent="submit">
                    <section>
                        <h3 class="font-semibold text-gray-800">Réseaux sociaux</h3>
                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <InputLabel for="social_facebook" value="Facebook" />
                                <InputText id="social_facebook" v-model="form.social_facebook" class="mt-1 w-full" />
                                <InputError :message="form.errors.social_facebook" />
                            </div>
                            <div>
                                <InputLabel for="social_instagram" value="Instagram" />
                                <InputText id="social_instagram" v-model="form.social_instagram" class="mt-1 w-full" />
                                <InputError :message="form.errors.social_instagram" />
                            </div>
                            <div>
                                <InputLabel for="social_youtube" value="YouTube" />
                                <InputText id="social_youtube" v-model="form.social_youtube" class="mt-1 w-full" />
                                <InputError :message="form.errors.social_youtube" />
                            </div>
                            <div>
                                <InputLabel for="social_tiktok" value="TikTok" />
                                <InputText id="social_tiktok" v-model="form.social_tiktok" class="mt-1 w-full" />
                                <InputError :message="form.errors.social_tiktok" />
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-800">Adresse</h3>
                        <div class="mt-4">
                            <InputText id="address" v-model="form.address" class="w-full" />
                            <InputError :message="form.errors.address" />
                        </div>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-800">Acompte et prix (CHF)</h3>
                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <div>
                                <InputLabel for="deposit_amount" value="Montant de l'acompte" />
                                <InputNumber id="deposit_amount" v-model="depositAmountChf" mode="currency"
                                    currency="CHF" locale="fr-CH" class="mt-1 w-full" />
                                <InputError :message="form.errors.deposit_amount" />
                            </div>
                            <div>
                                <InputLabel for="price_range_min" value="Prix minimum" />
                                <InputNumber id="price_range_min" v-model="priceRangeMinChf" mode="currency"
                                    currency="CHF" locale="fr-CH" class="mt-1 w-full" />
                                <InputError :message="form.errors.price_range_min" />
                            </div>
                            <div>
                                <InputLabel for="price_range_max" value="Prix maximum" />
                                <InputNumber id="price_range_max" v-model="priceRangeMaxChf" mode="currency"
                                    currency="CHF" locale="fr-CH" class="mt-1 w-full" />
                                <InputError :message="form.errors.price_range_max" />
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-800">SEO par défaut</h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <InputLabel for="default_seo_title" value="Titre par défaut" />
                                <InputText id="default_seo_title" v-model="form.default_seo_title"
                                    class="mt-1 w-full" />
                                <InputError :message="form.errors.default_seo_title" />
                            </div>
                            <div>
                                <InputLabel for="default_seo_description" value="Description par défaut" />
                                <textarea id="default_seo_description" v-model="form.default_seo_description" rows="3"
                                    class="mt-1 w-full rounded-md border-gray-300" />
                                <InputError :message="form.errors.default_seo_description" />
                            </div>
                        </div>
                    </section>

                    <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
