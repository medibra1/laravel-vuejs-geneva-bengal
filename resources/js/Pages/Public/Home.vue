<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsletterForm from '@/Components/NewsletterForm.vue';
import heroImage from '../../../images/home/hero.jpg';
import heroImageMobile from '../../../images/home/hero-mobile.jpg';
import kittensMontage from '../../../images/home/kittens-montage.png';
import newsletterKitten from '../../../images/home/newsletter-kitten.png';
import internationalKitten from '../../../images/home/international-kitten.png';
import instagram1 from '../../../images/home/instagram-1.jpg';
import instagram2 from '../../../images/home/instagram-2.jpg';
import instagram3 from '../../../images/home/instagram-3.jpg';
import instagram4 from '../../../images/home/instagram-4.jpg';
import instagram5 from '../../../images/home/instagram-5.jpg';
import instagram6 from '../../../images/home/instagram-6.jpg';

defineProps<{
    seo: { title: string; description: string };
}>();

const instagramTiles = [instagram1, instagram2, instagram3, instagram4, instagram5, instagram6];

// No dedicated pages/routes yet for these (international shipping info,
// the real Instagram feed) — placeholders, same convention as
// PublicLayout.vue's own `placeholderHref`.
const placeholderHref = '#';
</script>

<template>
    <Head :title="seo.title">
        <meta head-key="description" name="description" :content="seo.description" />
    </Head>

    <PublicLayout>
        <section class="relative flex h-[60vh] min-h-[420px] items-center justify-center overflow-hidden bg-neutral-900 text-white sm:h-[70vh]">
            <picture>
                <source :srcset="heroImageMobile" media="(max-width: 767px)" />
                <img :src="heroImage" alt="" class="absolute inset-0 h-full w-full object-cover opacity-70" />
            </picture>
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-900/90 via-neutral-900/30 to-transparent" />
            <div class="relative px-6 text-center">
                <p class="font-serif text-4xl italic text-emerald-300 sm:text-5xl">Éleveur de chats</p>
                <h1 class="mt-1 text-3xl font-semibold uppercase tracking-widest sm:text-5xl">Bengal</h1>
                <p class="mt-4 text-lg sm:text-xl">Créateur de bonheur depuis 2020</p>
                <p class="mx-auto mt-4 max-w-xl text-sm text-neutral-200 sm:text-base">
                    Nous élevons des chatons Bengal en parfaite santé, avec une apparence et un comportement à vous
                    faire rêver.
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="font-serif text-3xl italic text-emerald-700">Faits avec amour</h2>
                    <h3 class="mt-2 text-xl font-semibold uppercase tracking-wide text-neutral-900">
                        Nous avons des chatons !
                    </h3>
                    <p class="mt-4 text-neutral-600">
                        Découvrez les irrésistibles chatons de notre élevage Geneva Bengal et voyez pourquoi ils sont
                        si exceptionnels. Vous verrez ces jolis petits félins dans toutes sortes de couleurs rares et
                        spectaculaires. Qui sait ? Peut-être que vous trouverez ici le chaton Bengal de vos rêves !
                    </p>
                    <Link
                        :href="route('cats.index')"
                        class="mt-6 inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800"
                    >
                        Chatons Bengal disponibles
                        <span aria-hidden="true">→</span>
                    </Link>

                    <div class="mt-10 space-y-6">
                        <div>
                            <h4 class="font-semibold text-neutral-900">Éleveur professionnel de chats Bengal</h4>
                            <p class="mt-1 text-sm font-medium text-emerald-700">Qualité exceptionnelle</p>
                            <p class="mt-1 text-neutral-600">
                                Des chats et chatons Bengal de toutes les couleurs et d'une beauté incomparable,
                                reconnus à travers le monde.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-neutral-900">Santé</h4>
                            <p class="mt-1 text-neutral-600">
                                Des chats Bengal reproducteurs qui passent par un processus complet de tests pour
                                assurer leur bonne santé et celle de leurs chatons.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-neutral-900">Tempérament</h4>
                            <p class="mt-1 text-neutral-600">
                                Des chatons Bengal faits avec amour qui vous séduiront tant par leur charmante
                                personnalité que par leur comportement attachant.
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <img :src="kittensMontage" alt="Chatons Bengal de l'élevage Geneva Bengal" class="w-full rounded-lg" />
                </div>
            </div>
        </section>

        <section class="bg-neutral-900 py-16 text-white sm:py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="font-serif text-3xl italic text-emerald-300">Témoignages</h2>
                <h3 class="mt-2 text-xl font-semibold uppercase tracking-wide">
                    Un petit mot provenant de nos familles
                </h3>
                <p class="mt-4 text-neutral-300">
                    Nous sommes extrêmement ravis d'avoir fait autant de familles heureuses avec leurs nouveaux
                    chatons. Savoir que nous réussissons à créer du bonheur chez tant de personnes nous touche droit
                    au cœur. Voici les témoignages provenant des familles et éleveurs qui ont adopté un ou même deux
                    chats chez Geneva Bengal.
                </p>
                <Link
                    :href="`${route('pages.a-propos')}#temoignages`"
                    class="mt-6 inline-flex items-center gap-2 rounded-md border border-emerald-400 px-6 py-3 font-medium text-emerald-300 hover:bg-emerald-900/40"
                >
                    Voir tous les témoignages
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </section>

        <section class="border-b border-gray-200 bg-gray-50 py-16 sm:py-24">
            <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-6 lg:grid-cols-2">
                <div>
                    <h2 class="font-serif text-3xl italic text-emerald-700">Soyez les premiers au courant</h2>
                    <h3 class="mt-2 text-xl font-semibold uppercase tracking-wide text-neutral-900">
                        Abonnez-vous à notre infolettre !
                    </h3>
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
                    <h2 class="font-serif text-3xl italic text-emerald-700">Nos chatons partout dans le monde</h2>
                    <h3 class="mt-2 text-xl font-semibold uppercase tracking-wide text-neutral-900">
                        Nos chatons Bengal ont conquis le monde !
                    </h3>
                    <p class="mt-4 text-neutral-600">
                        Nos magnifiques petites bêtes vivent maintenant chez des familles ou des éleveurs de chats
                        Bengal situés un peu partout sur la planète. Nous nous occupons de toutes les démarches
                        concernant la livraison internationale ; vous n'aurez qu'à aller chercher votre nouveau
                        compagnon à l'aéroport le plus près de chez vous. Nous nous assurons que votre chaton Bengal
                        voyage confortablement de notre élevage jusqu'à vous et qu'il arrive en toute sécurité.
                    </p>
                    <a
                        :href="placeholderHref"
                        class="mt-6 inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800"
                    >
                        À propos de l'expédition internationale
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
                <div>
                    <img :src="internationalKitten" alt="" class="mx-auto w-full max-w-sm rounded-full" />
                </div>
            </div>
        </section>

        <section class="bg-emerald-700 py-10 text-white">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-center gap-4 px-6 text-center sm:flex-row sm:justify-between sm:text-left">
                <div>
                    <h2 class="font-serif text-2xl italic">Nous sommes social</h2>
                    <h3 class="text-lg font-semibold uppercase tracking-wide">Suivez-nous !</h3>
                </div>
                <ul class="flex gap-6 text-2xl">
                    <li><a :href="placeholderHref" title="Suivez-nous sur Facebook" aria-label="Facebook">FB</a></li>
                    <li><a :href="placeholderHref" title="Suivez-nous sur Instagram" aria-label="Instagram">IG</a></li>
                    <li><a :href="placeholderHref" title="Suivez-nous sur Youtube" aria-label="Youtube">YT</a></li>
                    <li><a :href="placeholderHref" title="Suivez-nous sur Pinterest" aria-label="Pinterest">PIN</a></li>
                </ul>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                <div class="grid grid-cols-3 gap-2">
                    <a
                        v-for="(tile, index) in instagramTiles"
                        :key="index"
                        :href="placeholderHref"
                        class="aspect-square overflow-hidden rounded"
                    >
                        <img :src="tile" alt="" class="h-full w-full object-cover transition hover:scale-105" />
                    </a>
                </div>
                <div>
                    <h2 class="font-serif text-3xl italic text-emerald-700">Suivez nos aventures</h2>
                    <h3 class="mt-2 text-xl font-semibold uppercase tracking-wide text-neutral-900">
                        Notre page Instagram
                    </h3>
                    <p class="mt-4 text-neutral-600">
                        Suivez les aventures de nos spectaculaires chats et chatons Bengal. Soyez prêts pour des
                        moments éblouissants et vraiment mignons.
                    </p>
                    <a
                        :href="placeholderHref"
                        class="mt-6 inline-flex items-center gap-2 rounded-md bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800"
                    >
                        Suivez-nous sur Instagram
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
