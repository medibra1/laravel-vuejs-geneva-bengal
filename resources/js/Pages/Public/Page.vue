<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Select from 'primevue/select';
import type { PageProps } from '@/types';

interface PublicTestimonial {
    id: number;
    author_name: string;
    quote: string;
    rating: number | null;
}

interface PublicSettings {
    address: string | null;
    social_facebook: string | null;
    social_instagram: string | null;
    social_youtube: string | null;
    social_pinterest: string | null;
}

interface PrefilledCat {
    id: number;
    name: string;
}

const props = defineProps<{
    page: {
        slug: string;
        title: string;
        body: string | null;
        meta_title: string | null;
        meta_description: string | null;
    };
    testimonials?: PublicTestimonial[];
    settings?: PublicSettings;
    prefilledCat?: PrefilledCat | null;
}>();

const pageCtx = usePage<PageProps>();
const honeypot = pageCtx.props.honeypot;

const reasonOptions = [
    { label: 'Adopter un chaton', value: 'adopt' },
    { label: "S'inscrire en liste d'attente", value: 'waiting_list' },
    { label: 'Question', value: 'question' },
];

const contactForm = useForm({
    name: '',
    email: '',
    reason: props.prefilledCat ? 'adopt' : 'question',
    cat_id: props.prefilledCat?.id ?? null,
    city: '',
    message: props.prefilledCat ? `Je suis intéressé(e) par ${props.prefilledCat.name}.` : '',
    [honeypot.nameFieldName]: '',
    [honeypot.validFromFieldName]: honeypot.encryptedValidFrom,
});

function submitContact(): void {
    contactForm.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => contactForm.reset('name', 'email', 'city', 'message'),
    });
}
</script>

<template>

    <Head :title="page.meta_title ?? page.title">
        <meta v-if="page.meta_description" head-key="description" name="description" :content="page.meta_description" />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-4xl px-6 py-16">
            <h1 class="text-3xl font-semibold text-neutral-900">{{ page.title }}</h1>
            <div v-if="page.body" class="prose mt-6 max-w-none whitespace-pre-line text-neutral-700">
                {{ page.body }}
            </div>
        </section>

        <section v-if="testimonials" id="temoignages" class="border-t border-gray-200 bg-gray-50 py-16">
            <div class="mx-auto max-w-4xl px-6">
                <h2 class="text-2xl font-semibold text-neutral-900">Témoignages</h2>
                <div v-if="testimonials.length" class="mt-8 space-y-8">
                    <blockquote v-for="testimonial in testimonials" :key="testimonial.id">
                        <p class="text-lg italic text-neutral-700">&ldquo;{{ testimonial.quote }}&rdquo;</p>
                        <footer class="mt-2 text-sm text-neutral-500">
                            — {{ testimonial.author_name }}
                            <span v-if="testimonial.rating"> ({{ testimonial.rating }}/5)</span>
                        </footer>
                    </blockquote>
                </div>
                <p v-else class="mt-4 text-neutral-500">Aucun témoignage pour le moment.</p>
            </div>
        </section>

        <section v-if="testimonials" class="border-t border-gray-200 py-16">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="text-2xl font-semibold text-neutral-900">Soyez les premiers au courant</h2>
                <p class="mt-2 text-neutral-600">
                    Abonnez-vous à notre infolettre pour être informé quand de nouveaux chatons sont disponibles.
                </p>
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>

        <section v-if="settings" class="border-t border-gray-200 py-16">
            <div class="mx-auto grid max-w-4xl gap-12 px-6 md:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-semibold text-neutral-900">Nous contacter</h2>
                    <p v-if="settings.address" class="mt-4 text-neutral-700">{{ settings.address }}</p>
                    <ul class="mt-4 flex gap-4 text-sm text-emerald-700">
                        <li v-if="settings.social_facebook"><a :href="settings.social_facebook"
                                target="_blank">Facebook</a></li>
                        <li v-if="settings.social_instagram"><a :href="settings.social_instagram"
                                target="_blank">Instagram</a></li>
                        <li v-if="settings.social_youtube"><a :href="settings.social_youtube"
                                target="_blank">YouTube</a></li>
                        <li v-if="settings.social_pinterest"><a :href="settings.social_pinterest"
                                target="_blank">Pinterest</a></li>
                    </ul>
                </div>

                <form class="space-y-4" @submit.prevent="submitContact">
                    <div v-if="honeypot.enabled" style="display: none" aria-hidden="true">
                        <input :id="honeypot.nameFieldName"
                            v-model="(contactForm as Record<string, string>)[honeypot.nameFieldName]" type="text"
                            tabindex="-1" autocomplete="off" />
                        <input v-model="(contactForm as Record<string, string>)[honeypot.validFromFieldName]"
                            type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <div v-if="prefilledCat" class="rounded-md bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                        À propos de : <strong>{{ prefilledCat.name }}</strong>
                    </div>

                    <div>
                        <InputLabel for="name" value="Nom" />
                        <input id="name" v-model="contactForm.name" class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="E-mail" />
                        <input id="email" v-model="contactForm.email" type="email"
                            class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="reason" value="Motif" />
                        <Select id="reason" v-model="contactForm.reason" :options="reasonOptions" option-label="label"
                            option-value="value" class="mt-1 w-full" />
                        <InputError :message="contactForm.errors.reason" />
                    </div>

                    <div>
                        <InputLabel for="city" value="Ville (optionnel)" />
                        <input id="city" v-model="contactForm.city" class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.city" />
                    </div>

                    <div>
                        <InputLabel for="message" value="Message" />
                        <textarea id="message" v-model="contactForm.message" rows="5"
                            class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.message" />
                    </div>

                    <PrimaryButton :disabled="contactForm.processing">Envoyer</PrimaryButton>
                    <p v-if="contactForm.recentlySuccessful" class="text-sm text-emerald-700">
                        Votre message a bien été envoyé.
                    </p>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
