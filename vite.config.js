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
    server: {
        // `npm run dev -- --host` (see docker-compose.yml's frontend
        // service, which also passes --port 5273) binds Vite to 0.0.0.0 so
        // the container's port mapping can reach it — but without `origin`
        // set explicitly, Laravel's Vite plugin has to *guess* the
        // browser-facing URL for @vite/client and every HMR-served module,
        // and guesses the literal bind address ("http://[::]:5273")
        // instead of "http://localhost:5273". The browser then loads those
        // scripts from a different origin than the page itself, which is
        // exactly what triggered the CORS failures reported alongside
        // Stripe's "elements.submit() must be called..." error.
        //
        // Read from process.env.VITE_DEV_PORT (set in docker-compose.yml's
        // frontend service, see there) rather than hardcoded to 5273 — so
        // this also works running `npm run dev` directly on the host,
        // outside Docker. That path never sets this variable and never
        // passes --port, so Vite falls back to its own default (5173); a
        // value hardcoded to the Docker port here previously pointed the
        // browser at a port nothing was listening on when running on the
        // host (`php artisan serve` + `npm run dev`, see CLAUDE.md — this
        // broke that setup right after the Docker-only fix above). Vite's
        // CLI doesn't expose whatever --port it was actually started with
        // as an env var on its own, hence the separate docker-compose.yml
        // variable rather than reading a Vite-provided one directly.
        origin: `http://localhost:${process.env.VITE_DEV_PORT ?? 5173}`,
        // The app itself is served by nginx on :8280 in Docker (see
        // docker-compose.yml) — a different origin than Vite's own dev
        // server port — by default Vite's CORS middleware only allows its
        // own origin, so the browser blocks every @vite/client / HMR
        // module fetch from a page loaded at :8280 even once `origin`
        // above points requests there correctly. `cors: true` has Vite
        // reflect whatever Origin the browser actually sent instead of
        // hardcoding its own, which is what a same-origin *response*
        // header actually needs to say to satisfy the browser's CORS
        // check — harmless for the host setup too, where Vite and the app
        // already share the same origin.
        cors: true,
    },
    test: {
        environment: "jsdom",
        globals: true,
    },
});
