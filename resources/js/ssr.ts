import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { renderToString } from "@vue/server-renderer";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createSSRApp, DefineComponent, h } from "vue";
import { createPinia } from "pinia";
import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import Aura from "@primeuix/themes/aura";
import { createI18n } from "vue-i18n";
import { ZiggyVue } from "ziggy-js";
import fr from "./locales/fr.json";
import en from "./locales/en.json";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

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
        setup({ App, props, plugin }) {
            const i18n = createI18n({
                legacy: false,
                locale: props.initialPage.props.locale as string,
                fallbackLocale: "en",
                messages: { fr, en },
            });

            // The client reads routes off `window.Ziggy` (injected by the
            // `@routes` Blade directive) — that global doesn't exist here,
            // so ZiggyVue needs the config passed explicitly instead. See
            // the matching `ziggy` shared prop in HandleInertiaRequests.
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(createPinia())
                .use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: ".dark" } } })
                .use(ToastService)
                .use(ZiggyVue, props.initialPage.props.ziggy as Parameters<typeof ZiggyVue.install>[1])
                .use(i18n);
        },
    }),
);
