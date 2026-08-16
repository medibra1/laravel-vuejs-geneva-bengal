# SEO

Fixes a real, observed problem on the production site being replaced
(genevabengals.ch): every page shared the same `<title>`/meta description
because the frontend was a client-rendered SPA with no per-page HTML —
bad for indexing individual kitten listings.

## Server-side rendering (SSR)

Inertia SSR (`resources/js/ssr.ts`) — each page's meta tags (`<Head
:title>`, description) are present in the HTML actually served, not
injected after the JS bundle runs. See
[architecture.md](architecture.md#rendering-client--ssr) for how `ssr.ts`
differs from `app.ts` (notably: resolves routes from the `ziggy` Inertia
prop instead of `window.Ziggy`, which only exists client-side).

On the target shared hosting, the SSR process is a persistent Node.js
site managed through Infomaniak's own Manager UI, not a Supervisor-managed
daemon — see [../DEPLOY.md](../DEPLOY.md) §2.

## Sitemap (`spatie/laravel-sitemap`)

One canonical `GET /sitemap.xml` (`Public\SitemapController`), cached one
hour (`Cache::remember`). Lists every static public path, every kitten and
breeder cat (`Cat::whereIn('type', [Kitten, Breeder])`), and every
published CMS page except `a-propos`/`contact` (those two are already in
the static path list under their canonical URLs). One `<url>` entry **per
locale**, each cross-linking to the other via `<xhtml:link rel="alternate"
hreflang="...">` — built directly rather than through
`Route::localizedSwitcherUrl()`, since that helper assumes an actual
current route being visited, which doesn't apply while building a sitemap
for every route at once.

## hreflang alternates on every page

`HandleInertiaRequests::share()`'s `alternateUrls` prop — empty array on
non-localized routes (admin, auth; see [i18n.md](i18n.md#layer-1--routing-niels-numberslaravel-localizer)
for why those aren't localized at all), otherwise one entry per supported
locale via `Route::localizedSwitcherUrl($locale)`. Tested in
`tests/Feature/HreflangTest.php`.

## Per-page meta

Public pages set their own `<Head :title>` / meta description rather than
inheriting a global default — `Page.meta_title`/`meta_description` are
translatable columns (see [domain-model.md](domain-model.md#cms-content--page-faqitem-testimonial-sitesetting))
for CMS-driven pages; cat detail pages derive their own from the cat's
name/description. `site_settings` holds the site-wide default SEO text
used as a fallback (`default_seo_title`, etc.) where no page-specific
value is set.

## `robots.txt` / `llms.txt`

`public/robots.txt` references the sitemap, disallows the back-office and
auth routes for all crawlers, and adds explicit named blocks for
GPTBot/ClaudeBot/CCBot/Google-Extended/PerplexityBot — a named block
*replaces* the wildcard `*` block for that bot rather than merging with
it, so each one repeats the same `Disallow` rules rather than only
appearing once. `public/llms.txt` is a bilingual markdown summary
following the [llmstxt.org](https://llmstxt.org) convention.

Both are static files under `public/` — confirmed (checksum before/after)
that `npm run build` never touches them (`laravel-vite-plugin` sets
`publicDir: false`, only writes to `public/build/`).
