import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath, URL } from "node:url";

export default defineConfig({
    plugins: [
        // Vitest boots its own internal Vite server to run tests, and the
        // laravel plugin's dev-server-in-CI guard fires there too — it has
        // nothing to do with the actual asset build, so skip it under Vitest.
        ...(process.env.VITEST ? [] : [laravel({ input: "resources/js/app.ts", ssr: "resources/js/ssr.ts", refresh: true })]),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        // The laravel plugin normally provides this "@" -> resources/js
        // alias itself, but it's skipped under Vitest (see above), so any
        // component under test that itself imports via "@/..." would
        // otherwise fail to resolve. Declared explicitly here so it holds
        // regardless of which plugins are active.
        alias: {
            "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
        },
    },
    test: {
        environment: "jsdom",
        globals: true,
    },
});
