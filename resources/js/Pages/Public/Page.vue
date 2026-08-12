<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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
    prefilledCat?: PrefilledCat | null;
    faqItems?: PublicFaqItem[];
}>();

const pageCtx = usePage<PageProps>();
const honeypot = pageCtx.props.honeypot;
const address = computed(() => pageCtx.props.address);
const phone = computed(() => pageCtx.props.phone);
const email = computed(() => pageCtx.props.email);
const socialLinks = computed(() => pageCtx.props.socialLinks);
const { t } = useI18n();

// Single-open accordion, same on every breakpoint — the reference site
// (genevabengals.ch/chaton-bengal-a-vendre) only does this toggle on
// mobile and shows a flat grid on desktop; ours uses it everywhere.
const openFaqId = ref<number | null>(null);

function toggleFaq(id: number): void {
    openFaqId.value = openFaqId.value === id ? null : id;
}

const reasonOptions = computed(() => [
    { label: t('contact.reason_adopt'), value: 'adopt' },
    { label: t('contact.reason_waiting_list'), value: 'waiting_list' },
    { label: t('contact.reason_question'), value: 'question' },
]);

const contactForm = useForm({
    name: '',
    email: '',
    reason: props.prefilledCat ? 'adopt' : 'question',
    cat_id: props.prefilledCat?.id ?? null,
    city: '',
    message: props.prefilledCat ? t('contact.prefilled_message', { name: props.prefilledCat.name }) : '',
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

        <section v-if="faqItems" class="border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto flex max-w-3xl justify-center px-6">
                <div class="w-full">
                    <div v-if="faqItems.length" class="flex flex-col gap-4">
                        <div v-for="item in faqItems" :key="item.id" class="rounded-[10px] bg-white p-4 shadow-sm">
                            <h3
                                class="font-heading text-brand-gray flex h-[50px] cursor-pointer items-center justify-between gap-4 text-sm font-semibold tracking-wide uppercase"
                                @click="toggleFaq(item.id)"
                            >
                                {{ item.question }}
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-brand-green text-white transition-transform duration-300 ease-in-out"
                                    :class="{ 'rotate-45': openFaqId === item.id }"
                                >
                                    <i class="pi pi-plus text-xs" />
                                </span>
                            </h3>
                            <div
                                class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                                :class="openFaqId === item.id ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <p class="mt-2 text-neutral-700 opacity-0 transition-opacity duration-300 ease-in-out" :class="{ 'opacity-100': openFaqId === item.id }">
                                        {{ item.answer }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-neutral-500">{{ $t('faq.empty') }}</p>
                </div>
            </div>
        </section>

        <section v-if="testimonials" id="temoignages" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-6 text-center">
                <SectionHeading :script="$t('testimonials.section_script')" center />
                <div v-if="testimonials.length" class="mt-8 space-y-8 text-left">
                    <blockquote v-for="testimonial in testimonials" :key="testimonial.id">
                        <p class="text-lg italic text-neutral-700">&ldquo;{{ testimonial.quote }}&rdquo;</p>
                        <footer class="mt-2 text-sm text-neutral-500">
                            — {{ testimonial.author_name }}
                            <span v-if="testimonial.rating"> ({{ testimonial.rating }}/5)</span>
                        </footer>
                    </blockquote>
                </div>
                <p v-else class="mt-4 text-neutral-500">{{ $t('testimonials.empty') }}</p>
            </div>
        </section>

        <section v-if="testimonials" class="border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading :script="$t('newsletter.section_script')" :title="$t('newsletter.section_title')" center />
                <div class="mt-6 flex justify-center">
                    <NewsletterForm class="w-full max-w-md" />
                </div>
            </div>
        </section>

        <section v-if="page.slug === 'contact'" class="bg-brand-canvas border-t border-gray-200 py-16 sm:py-24">
            <div class="mx-auto grid max-w-4xl gap-12 px-6 md:grid-cols-2">
                <div>
                    <h2 class="font-heading text-brand-gray text-2xl font-bold uppercase tracking-wide">{{ $t('contact.heading') }}</h2>
                    <p v-if="address" class="mt-4 text-neutral-700">{{ address }}</p>
                    <p v-if="phone" class="mt-2 text-neutral-700">
                        <a :href="`tel:${phone}`" class="hover:text-brand-green">{{ phone }}</a>
                    </p>
                    <p v-if="email" class="mt-2 text-neutral-700">
                        <a :href="`mailto:${email}`" class="hover:text-brand-green">{{ email }}</a>
                    </p>
                    <SocialLinks class="[&_a]:hover:text-brand-green mt-4 text-brand-gray" v-bind="socialLinks" />
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
                        {{ $t('contact.about_cat') }} <strong>{{ prefilledCat.name }}</strong>
                    </div>

                    <div>
                        <InputLabel for="name" :value="$t('contact.label_name')" />
                        <input id="name" v-model="contactForm.name" class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" :value="$t('contact.label_email')" />
                        <input id="email" v-model="contactForm.email" type="email"
                            class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="reason" :value="$t('contact.label_reason')" />
                        <Select id="reason" v-model="contactForm.reason" :options="reasonOptions" option-label="label"
                            option-value="value" class="mt-1 w-full" />
                        <InputError :message="contactForm.errors.reason" />
                    </div>

                    <div>
                        <InputLabel for="city" :value="$t('contact.label_city')" />
                        <input id="city" v-model="contactForm.city" class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.city" />
                    </div>

                    <div>
                        <InputLabel for="message" :value="$t('contact.label_message')" />
                        <textarea id="message" v-model="contactForm.message" rows="5"
                            class="mt-1 w-full rounded-md border-gray-300" />
                        <InputError :message="contactForm.errors.message" />
                    </div>

                    <button type="submit" class="btn-outline-brand" :disabled="contactForm.processing">{{ $t('contact.submit') }}</button>
                    <p v-if="contactForm.recentlySuccessful" class="text-brand-green text-sm">
                        {{ $t('contact.success') }}
                    </p>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
