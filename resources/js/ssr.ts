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

// See app.ts for why only the active locale is loaded, and why `any`
// (not `unknown`) — vue-i18n's `messages` option needs assignable values.
type LocaleMessages = Record<string, any>;
const localeLoaders: Record<string, () => Promise<{ default: LocaleMessages }>> = {
    fr: () => import("./locales/fr.json"),
    en: () => import("./locales/en.json"),
};

createServer(async (page) => {
    // Inertia's SSR `setup()` must return an App synchronously (its return
    // type is `App`, not `Promise<App>`), so the locale file has to be
    // awaited out here — before createInertiaApp is even called — rather
    // than inside setup() itself. `page.props.locale` is already available
    // on the raw page payload at this point, same value setup() would've
    // read off `props.initialPage.props.locale`.
    const locale = (page.props.locale as string) ?? "fr";
    const { default: messages } = await (localeLoaders[locale] ?? localeLoaders.fr)();

    return createInertiaApp({
        page,
        title: (title) => `${title} - ${appName}`,
        render: renderToString,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
            ),
        setup({ App, props, plugin }) {
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
    });
});
