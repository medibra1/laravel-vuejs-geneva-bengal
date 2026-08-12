import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import { ViteImageOptimizer } from "vite-plugin-image-optimizer";
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
        // Only covers hardcoded static images (shared/, admin/, and a few
        // fixed home/ images) — resources/images/home/slider-*.jpg and
        // home/social/* are excluded on purpose: they're being migrated to
        // database-managed (admin-uploaded) content and will leave this
        // folder, so optimizing them here would be wasted/dead config.
        //
        // `include` as an array of strings is matched against the plain
        // output basename (e.g. "logo.png", no hash/directory — see this
        // plugin's areFilesMatching()/generateBundle hook). A RegExp instead
        // matches against the *hashed* bundle path (e.g.
        // "assets/logo-C5FXrMMg.png"), which an anchored `^...png$` pattern
        // can never match — the string-array form is the one that actually
        // targets these exact source files.
        ViteImageOptimizer({
            include: [
                // resources/images/shared/*
                "chaton-mobile-white.png",
                "chaton-mobile.png",
                "logo-footer.png",
                "logo-gb.png",
                // resources/images/admin/*
                "logo-dark-text.png",
                // shared/logo.png and admin/logo.png share this basename
                "logo.png",
                // resources/images/home/*
                "cat-head.png",
                "cat-head2.png",
                "kittens-montage.png",
                "newsletter-kitten.png",
                "international-kitten.png",
            ],
            // sharp's PNG encoder only quantizes to a palette (pngquant-style)
            // when `palette: true` is set alongside `quality`.
            png: { quality: 75, palette: true },
            // sharp's JPEG encoder is mozjpeg-based already; `quality` is all
            // that's needed to get the mozjpeg-equivalent behavior.
            jpeg: { quality: 75, mozjpeg: true },
            jpg: { quality: 75, mozjpeg: true },
            svg: {
                multipass: true,
            },
        }),
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
