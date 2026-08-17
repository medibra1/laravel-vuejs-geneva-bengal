import "../css/app.css";
import "primeicons/primeicons.css";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, DefineComponent, h } from "vue";
import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import Aura from "@primeuix/themes/aura";
import { createI18n } from "vue-i18n";
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

// Only one of fr.json/en.json is ever needed for a given page load —
// Inertia re-resolves this from the server on every navigation anyway
// (see the locale-switch links in PublicLayout.vue, which do a full
// browser navigation), so there's no case where the other locale's
// strings are needed without a fresh app.ts boot to go with it.
// `Record<string, any>` (not `unknown`) matches what a static
// `import fr from "./locales/fr.json"` used to infer — vue-i18n's
// `messages` option needs assignable values, `unknown` isn't one.
type LocaleMessages = Record<string, any>;
const localeLoaders: Record<string, () => Promise<{ default: LocaleMessages }>> = {
    fr: () => import("./locales/fr.json"),
    en: () => import("./locales/en.json"),
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
        ),
    async setup({ el, App, props, plugin }) {
        const locale = props.initialPage.props.locale as string;
        const { default: messages } = await (localeLoaders[locale] ?? localeLoaders.fr)();

        const i18n = createI18n({
            legacy: false,
            locale,
            fallbackLocale: locale,
            messages: { [locale]: messages },
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: ".dark" } } })
            .use(ToastService)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
