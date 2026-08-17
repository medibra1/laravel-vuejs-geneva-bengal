import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { renderToString } from "@vue/server-renderer";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createSSRApp, DefineComponent, h } from "vue";
import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import Aura from "@primeuix/themes/aura";
import { createI18n } from "vue-i18n";
import { ZiggyVue } from "ziggy-js";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

// See app.ts for why only the active locale is loaded.
type LocaleMessages = Record<string, unknown>;
const localeLoaders: Record<string, () => Promise<{ default: LocaleMessages }>> = {
    fr: () => import("./locales/fr.json"),
    en: () => import("./locales/en.json"),
};

createServer((page) =>
    createInertiaApp({
        page,
        title: (title) => `${title} - ${appName}`,
        render: renderToString,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
            ),
        async setup({ App, props, plugin }) {
            const locale = props.initialPage.props.locale as string;
            const { default: messages } = await (localeLoaders[locale] ?? localeLoaders.fr)();

            const i18n = createI18n({
                legacy: false,
                locale,
                fallbackLocale: locale,
                messages: { [locale]: messages },
            });

            // The client reads routes off `window.Ziggy` (injected by the
            // `@routes` Blade directive) — that global doesn't exist here,
            // so ZiggyVue needs the config passed explicitly instead. See
            // the matching `ziggy` shared prop in HandleInertiaRequests.
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: ".dark" } } })
                .use(ToastService)
                .use(ZiggyVue, props.initialPage.props.ziggy as Parameters<typeof ZiggyVue.install>[1])
                .use(i18n);
        },
    }),
);
