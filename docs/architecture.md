# Architecture

Monorepo Laravel + Inertia: one deployable unit, server-rendered navigation
state, no separate SPA/API split. This document describes how the pieces
fit together. For setup/commands see the [README](../README.md); for the
domain model see [domain-model.md](domain-model.md); for payments see
[payments.md](payments.md); for deployment see [../DEPLOY.md](../DEPLOY.md).

## Request lifecycle

```
Browser
  │
  ▼
routes/web.php or routes/admin.php
  │   (web middleware group, see bootstrap/app.php)
  ▼
SetLocale → RedirectLocale        (niels-numbers/laravel-localizer, public routes only)
  │
  ▼
HandleInertiaRequests::share()    (auth, locale, flash, notifications, nav data, ziggy)
  │
  ▼
Controller (thin) ──▶ Service/Model layer ──▶ Eloquent / external API (Stripe)
  │
  ▼
Inertia::render('Public/Xxx' | 'Admin/Xxx', [...props])
  │
  ▼
resources/js/Pages/**/*.vue  (client hydration, or SSR via resources/js/ssr.ts)
```

Two route files, both loaded from `routes/web.php`:
- `routes/web.php` — public site (`Route::localize()` block, `/fr/...`
  `/en/...`), the Stripe webhook (not locale-prefixed), the `/cron/run`
  HTTP-triggered scheduler endpoint, and the sitemap.
- `routes/admin.php` — back-office, not locale-prefixed (see
  [i18n.md](i18n.md) for why), split into three middleware tiers (see
  [Roles & permissions](#roles--permissions-authorization-layers) below).
- `routes/auth.php` — Laravel Breeze scaffolding, unchanged from install.

## Directory map

```
app/
├── Enums/                    CatSex, CatType, CatStatus, DepositStatus, PaymentMethod,
│                              ContactReason, ContactStatus, GalleryType
├── Models/                   Cat, Color, Owner, Litter, Gallery, Page, FaqItem,
│                              Testimonial, Deposit, PaymentIntentTracking, ContactRequest,
│                              NewsletterSubscriber, SiteSetting, EditorUpload, User
├── Http/
│   ├── Controllers/
│   │   ├── Public/           HomeController, CatController, ContactController,
│   │   │                     PageController, DepositController, GalleryController,
│   │   │                     LitterController, NewsletterController,
│   │   │                     StripeWebhookController, SitemapController
│   │   ├── Admin/            One controller per CRUD module + Cats/AdoptionCatController,
│   │   │                     Cats/BreederCatController (see domain-model.md — two admin
│   │   │                     sections sharing one Cat model), DashboardController,
│   │   │                     ActivityLogController, DepositController, UserController,
│   │   │                     SiteSettingController, NotificationController
│   │   └── Auth/              Breeze scaffolding
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php   shared Inertia props — see below
│   └── Requests/               one Form Request per write endpoint
├── Services/
│   ├── DashboardService.php      KPI/chart aggregation, pure Eloquent — see admin.md
│   └── Payments/                  PaymentGateway interface + StripeGateway,
│                                   DepositPaymentProcessor — see payments.md
├── Jobs/
│   └── ReconcileCheckouts.php     scheduled reconciliation — see payments.md
└── Notifications/                 mail + database channel, staff and client — see admin.md
```

```
resources/js/
├── Pages/
│   ├── Public/                Home, ChatonsDisponibles, ChatonDetail, Reproducteurs,
│   │                          PorteesPrevues, Page (generic CMS page), Galerie,
│   │                          DepositPay, DepositReturn, NewsletterUnsubscribed
│   └── Admin/                 Cats/Adoption, Cats/Breeders, Owners, Litters, Galleries,
│                              Pages, FaqItems, Testimonials, Deposits, Users, Settings,
│                              Dashboard, ActivityLog, ContactRequests,
│                              NewsletterSubscribers
├── Layouts/                    PublicLayout.vue, AdminLayout.vue
├── Components/                  shared building blocks (CatCard-equivalents, forms,
│                                FlashToast, NotificationBell, RichTextEditor...)
├── Composables/                  useTableQuery, useDepositActions, useImageSlider,
│                                useConfirmsPassword
├── locales/                     fr.json, en.json — UI strings only, see i18n.md
├── types/                        models.ts, index.d.ts (Inertia PageProps)
├── app.ts                       client entry — createInertiaApp + plugin stack
└── ssr.ts                       SSR entry — see below
```

## Rendering: client + SSR

Both `app.ts` and `ssr.ts` install the same plugin stack (PrimeVue +
`ToastService`, Ziggy, vue-i18n) — `ToastService` in particular must be on
both, or `useToast()` inside `FlashToast.vue` (mounted unconditionally in
both layouts) throws during SSR. `ssr.ts` resolves `route()` from the
`ziggy` prop shared by `HandleInertiaRequests` rather than
`window.Ziggy` (that global only exists client-side, injected by the
`@routes` Blade directive at first load). Pinia was installed from the
start of the project but never actually used (no `defineStore` anywhere
in the repo) — dropped entirely rather than left as dead weight.

Both entrypoints load only the active locale's JSON (`fr.json` or
`en.json`, not both) via a dynamic `import()`, resolved from
`page.props.locale`/`props.initialPage.props.locale` before mounting —
see the comments in `app.ts`/`ssr.ts` for why `ssr.ts`'s `setup()` has
to stay synchronous (Inertia's SSR return type is a strict `App`, not a
`Promise<App>`) while `app.ts`'s can be `async`.

SSR exists specifically to fix a production issue on the original site:
every page shared the same `<title>`/meta description, hurting indexation
of individual kitten listings. See [seo.md](seo.md).

## Shared Inertia props (`HandleInertiaRequests::share()`)

Every request carries a fixed set of props regardless of the page being
rendered, so components can rely on them being present without prop
drilling:

| Prop | Purpose |
|---|---|
| `auth.user` / `auth.roles` | current admin session, or `null` on public pages |
| `locale` | active locale (`fr`/`en`) — see [i18n.md](i18n.md) |
| `flash.success` / `flash.error` | one-shot toast messages, read by `FlashToast.vue` |
| `notifications` | unread count + last 10, admin only (`null` hides the bell) |
| `menuPages` | CMS pages with a `menu_group`, for the public dropdown nav |
| `colors` | full color list, for the public "browse by color" nav |
| `socialLinks`, `address`, `phone`, `email`, `logoUrl` | from `site_settings`, needed on every public page (header/footer) |
| `honeypot` | `spatie/laravel-honeypot` config, for public forms |
| `alternateUrls` | hreflang alternates for the current page — see [seo.md](seo.md) |
| `ziggy` | route table, including for SSR — see above |

Anything page-specific stays a per-controller prop instead (e.g. hero
slider images, via `Public\Concerns\SharesHeroSlides`, are per-controller
because only some public pages show a hero).

## Roles & permissions (authorization layers)

Two roles via `spatie/laravel-permission`, enforced by three separate
route-group tiers rather than per-action checks scattered through
controllers:

1. **`role:admin|super_admin`** (`routes/admin.php`, main group) — all
   business/CMS content: cats, owners, litters, galleries, colors, pages,
   FAQ, testimonials, contact requests, newsletter subscribers, deposits
   (read/create/finalize/mark-paid).
2. **`role:super_admin`** (separate group, same file) — admin accounts
   (`users.*`), site settings, activity log, and the money-moving deposit
   actions (`refund`, `cancel`, `finalize-directly`, `verify-stripe`).
3. **`password.confirm`** stacked on top of tier 2's sensitive actions
   (create/update/destroy a user, refund/cancel a deposit) — a stale
   confirmation (`config('auth.password_timeout')`) re-prompts even for an
   already-authenticated super_admin, so a walked-away session can't be
   used to move money or accounts.

`Gate::before` in `AppServiceProvider` short-circuits every check to `true`
for `super_admin`, so the codebase never enumerates permissions the
super_admin already implicitly has — see `RolesAndPermissionsSeeder`.

Route-model-binding is not enough to isolate the two Cat sections
(`AdoptionCatController` / `BreederCatController` share one `cats` table) —
each controller has its own `ensureAdoptionType()`/`ensureBreederType()`
guard (`abort_unless(..., 404)`) so guessing an id in the URL can't cross
sections.

## The `/cron/run` endpoint — why it exists

Target hosting (Infomaniak shared/mutualisé) has no crontab and no daemon
process — see [../DEPLOY.md](../DEPLOY.md) §2 and §4. `GET /cron/run?token=`
(token compared via `hash_equals()`, throttled) stands in for both a
`* * * * * php artisan schedule:run` cron entry and a queue worker daemon:
it calls `schedule:run`, drains the queue (`queue:work
--stop-when-empty`), and — because the external scheduler only guarantees
calling this URL at least every 15 minutes, not on `schedule:run`'s exact
`:00/:15/:30/:45` slots — also dispatches `ReconcileCheckouts` directly on
every single call (cheap and idempotent, see [payments.md](payments.md))
rather than only when the two happen to align. The monthly
`activitylog:clean` purge uses `Cache::add()` with a 30-day TTL instead,
since it's a single `DELETE` that doesn't need to run on every call — see
[admin.md](admin.md#activity-log).

## Testing layout

- Backend: Pest, under `tests/Feature/{Public,Admin}` mirroring the
  controller namespaces, plus `tests/Feature/*SeederTest.php` for the
  always-run seeders and `tests/Doubles/` for `FakePaymentGateway` /
  `FakeCaptureStripeGateway` (see [payments.md](payments.md#testing)).
- Frontend: Vitest + `@vue/test-utils`, colocated `__tests__/` folders next
  to the composables/pages they cover.
- CI: two independent, path-filtered GitHub Actions workflows
  (`backend.yml` never depends on `node_modules`, `frontend.yml` never on
  `vendor/`) — see `.github/workflows/`.
