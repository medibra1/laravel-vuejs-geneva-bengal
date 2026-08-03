import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        // laravel({ input: "resources/js/app.ts", refresh: true }),
                // Vitest boots its own internal Vite server to run tests, and the
        // laravel plugin's dev-server-in-CI guard fires there too — it has
        // nothing to do with the actual asset build, so skip it under Vitest.
        ...(process.env.VITEST ? [] : [laravel({ input: "resources/js/app.ts", refresh: true })]),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    test: {
        environment: "jsdom",
        globals: true,
    },
});
