<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import SectionHeading from '@/Components/SectionHeading.vue';
import HeroSlider from '@/Components/HeroSlider.vue';
import SocialLinks from '@/Components/SocialLinks.vue';
import type { PageProps } from '@/types';
// import slider1 from '../../../images/home/slider-1.jpg';
// import slider2 from '../../../images/home/slider-2.jpg';
import slider3 from '../../../images/home/slider-3.jpg';
import slider4 from '../../../images/home/slider-4.jpg';
import kittensMontage from '../../../images/home/kittens-montage.png';
import newsletterKitten from '../../../images/home/newsletter-kitten.png';
import internationalKitten from '../../../images/home/international-kitten.png';
import social1 from '../../../images/home/social/social-1.jpg';
import social2 from '../../../images/home/social/social-2.jpg';
import social3 from '../../../images/home/social/social-3.jpg';
import social4 from '../../../images/home/social/social-4.jpg';
import social5 from '../../../images/home/social/social-5.jpg';
import social6 from '../../../images/home/social/social-6.jpg';

defineProps<{
    seo: { title: string; description: string };
}>();

const socialTiles = [social1, social2, social3, social4, social5, social6];
const socialLinks = usePage<PageProps>().props.socialLinks;

// No dedicated pages/routes yet for these (international shipping info,
// the real social feeds) — placeholders, same convention as
// PublicLayout.vue's own `placeholderHref`.
const placeholderHref = '#';

const slides = [
    // slider1,
    // slider2,
    slider3, slider4];
</script>

<template>

    <Head :title="seo.title">
        <meta head-key="description" name="description" :content="seo.description" />
    </Head>

    <PublicLayout>
        <HeroSlider :slides="slides" />

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div>
                    <SectionHeading script="Faits avec amour" title="Nous avons des chatons !" />
                    <p class="mt-4 text-neutral-600">
                        Découvrez les irrésistibles chatons de notre élevage Geneva Bengal et voyez pourquoi ils sont
                        si exceptionnels. Vous verrez ces jolis petits félins dans toutes sortes de couleurs rares et
                        spectaculaires. Qui sait ? Peut-être que vous trouverez ici le chaton Bengal de vos rêves !
                    </p>
                    <Link :href="route('cats.index')" class="btn-outline-brand mt-6">
                        Chatons Bengal disponibles
                        <span aria-hidden="true">→</span>
                    </Link>

                    <div class="mt-10 space-y-6">
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">Qualité exceptionnelle</h4>
                            <p class="mt-1 text-neutral-600">
                                Des chats et chatons Bengal de toutes les couleurs et d'une beauté incomparable,
                                reconnus à travers le monde.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">Santé</h4>
                            <p class="mt-1 text-neutral-600">
                                Des chats Bengal reproducteurs qui passent par un processus complet de tests pour
                                assurer leur bonne santé et celle de leurs chatons.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-heading text-brand-green text-sm font-light">Tempérament</h4>
                            <p class="mt-1 text-neutral-600">
                                Des chatons Bengal faits avec amour qui vous séduiront tant par leur charmante
                                personnalité que par leur comportement attachant.
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <img :src="kittensMontage" alt="Chatons Bengal de l'élevage Geneva Bengal"
                        class="w-full rounded-lg" />
                </div>
            </div>
        </section>

        <section class="bg-brand-ink py-16 text-white sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <SectionHeading script="Témoignages" title="Un petit mot provenant de nos familles" inverted center />
                <p class="mt-4 text-neutral-300">
                    Nous sommes extrêmement ravis d'avoir fait autant de familles heureuses avec leurs nouveaux
                    chatons. Savoir que nous réussissons à créer du bonheur chez tant de personnes nous touche droit
                    au cœur. Voici les témoignages provenant des familles et éleveurs qui ont adopté un ou même deux
                    chats chez Geneva Bengal.
                </p>
                <Link :href="`${route('pages.a-propos')}#temoignages`"
                    class="btn-outline-brand mt-6 !border-white !text-white hover:!border-brand-green">
                    Voir tous les témoignages
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>

        <section class="bg-brand-canvas border-b border-gray-200 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-6 lg:grid-cols-2">
                <div>
                    <SectionHeading script="Soyez les premiers au courant" title="Abonnez-vous à notre infolettre !" />
                    <p class="mt-4 text-neutral-600">
                        Notre infolettre est la meilleure façon d'être informé dès qu'il y a de nouveaux chatons
                        disponibles sur notre site Web.
                    </p>
                    <NewsletterForm class="mt-6 max-w-md" />
                </div>
                <div>
                    <img :src="newsletterKitten" alt="" class="mx-auto w-full max-w-sm rounded-full" />
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div>
                    <SectionHeading script="Nos chatons partout dans le monde"
                        title="Nos chatons Bengal ont conquis le monde !" />
                    <p class="mt-4 text-neutral-600">
                        Nos magnifiques petites bêtes vivent maintenant chez des familles ou des éleveurs de chats
                        Bengal situés un peu partout sur la planète. Nous nous occupons de toutes les démarches
                        concernant la livraison internationale ; vous n'aurez qu'à aller chercher votre nouveau
                        compagnon à l'aéroport le plus près de chez vous. Nous nous assurons que votre chaton Bengal
                        voyage confortablement de notre élevage jusqu'à vous et qu'il arrive en toute sécurité.
                    </p>
                    <a :href="placeholderHref" class="btn-outline-brand mt-6">
                        À propos de l'expédition internationale
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
                <div>
                    <img :src="internationalKitten" alt="" class="mx-auto w-full max-w-sm rounded-full" />
                </div>
            </div>
        </section>

        <section class="bg-brand-green py-10 text-white">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-4 px-6 text-center sm:flex-row sm:justify-between sm:text-left">
                <div>
                    <h2 class="font-script text-3xl">Nous sommes social</h2>
                    <h3 class="font-heading text-lg font-semibold tracking-wide uppercase">Suivez-nous !</h3>
                </div>
                <SocialLinks v-bind="socialLinks" class="text-2xl [&_svg]:h-7 [&_svg]:w-7" />
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div class="grid grid-cols-3 gap-2">
                    <a v-for="(tile, index) in socialTiles" :key="index" :href="placeholderHref"
                        class="aspect-square overflow-hidden rounded">
                        <img :src="tile" alt="" class="h-full w-full object-cover transition hover:scale-105" />
                    </a>
                </div>
                <div>
                    <SectionHeading script="Suivez nos aventures" title="Notre page Facebook" />
                    <p class="mt-4 text-neutral-600">
                        Suivez les aventures de nos spectaculaires chats et chatons Bengal. Soyez prêts pour des
                        moments éblouissants et vraiment mignons.
                    </p>
                    <a :href="placeholderHref" class="btn-outline-brand mt-6">
                        Suivez-nous sur Facebook
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
