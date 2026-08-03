import "../css/app.css";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, DefineComponent, h } from "vue";
import { createPinia } from "pinia";
import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import { createI18n } from "vue-i18n";
import { ZiggyVue } from 'ziggy-js';
import fr from "./locales/fr.json";
import en from "./locales/en.json";
import "primeicons/primeicons.css";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        const i18n = createI18n({
            legacy: false,
            locale: props.initialPage.props.locale as string,
            fallbackLocale: "en",
            messages: { fr, en },
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(PrimeVue, { theme: { preset: Aura } })
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
