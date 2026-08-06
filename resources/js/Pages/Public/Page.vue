<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import SocialLinks from '@/Components/SocialLinks.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Select from 'primevue/select';
import type { PageProps } from '@/types';

interface PublicTestimonial {
    id: number;
    author_name: string;
    quote: string;
    rating: number | null;
}

interface PublicFaqItem {
    id: number;
    question: string;
    answer: string;
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
    faqItems?: PublicFaqItem[];
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
        <section class="mx-auto max-w-4xl px-6 py-16 sm:py-24">
            <SectionHeading :script="page.title" />
            <div
                v-if="page.body"
                class="mt-6 max-w-none text-neutral-700 [&_a]:text-brand-green [&_a]:underline [&_h2]:font-heading [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:uppercase [&_h2]:tracking-wide [&_h2]:text-brand-gray [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-semibold [&_img]:mt-4 [&_img]:max-w-full [&_img]:rounded-md [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-4 [&_p:first-child]:mt-0 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-6"
                v-html="page.body"
            />
        </section>

        <section v-if="faqItems" id="faq" class="border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6">
                <SectionHeading script="Questions fréquentes" center />
                <div v-if="faqItems.length" class="mt-8 divide-y divide-gray-200">
                    <details v-for="item in faqItems" :key="item.id" class="group py-4">
                        <summary
                            class="font-heading text-brand-gray flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-semibold tracking-wide uppercase [&::-webkit-details-marker]:hidden"
                        >
                            {{ item.question }}
                            <svg
                                viewBox="0 0 24 24"
                                class="h-3 w-3 shrink-0 transition group-open:rotate-180"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                            >
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </summary>
                        <p class="mt-3 text-neutral-700">{{ item.answer }}</p>
                    </details>
                </div>
                <p v-else class="mt-4 text-neutral-500">Aucune question pour le moment.</p>
            </div>
        </section>

        <section v-if="testimonials" id="temoignages" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-6 text-center">
                <SectionHeading script="Témoignages" center />
                <div v-if="testimonials.length" class="mt-8 space-y-8 text-left">
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

        <section v-if="testimonials" class="border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading script="Soyez les premiers au courant" title="Abonnez-vous à notre infolettre !" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>

        <section v-if="settings" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto grid max-w-4xl gap-12 px-6 md:grid-cols-2">
                <div>
                    <h2 class="font-heading text-brand-gray text-2xl font-bold uppercase tracking-wide">Nous contacter</h2>
                    <p v-if="settings.address" class="mt-4 text-neutral-700">{{ settings.address }}</p>
                    <SocialLinks
                        class="[&_a]:hover:text-brand-green mt-4 text-brand-gray"
                        :facebook="settings.social_facebook"
                        :instagram="settings.social_instagram"
                        :youtube="settings.social_youtube"
                        :pinterest="settings.social_pinterest"
                    />
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

                    <button type="submit" class="btn-outline-brand" :disabled="contactForm.processing">Envoyer</button>
                    <p v-if="contactForm.recentlySuccessful" class="text-brand-green text-sm">
                        Votre message a bien été envoyé.
                    </p>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
