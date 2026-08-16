# Back-office

See [architecture.md](architecture.md#roles--permissions-authorization-layers)
for the role/middleware tiers. This document covers the admin-specific
features that don't fit elsewhere.

## Admin account management (`super_admin` only)

`Admin\UserController` (`admin/users`, `role:super_admin` +
`password.confirm` on write actions). No password is ever set by the
super_admin creating the account — that would mean a plaintext password
traveling by email. Instead: the account is created with a random,
unusable password, and Laravel's native `Password::sendResetLink()` fires
immediately — the new admin picks their own password via the standard
reset flow. No custom invitation-token system.

Deactivation (`users.is_active`, default `true`) rather than deletion by
default — a disabled account can't log in (checked in the auth
provider/middleware) but stays visible in historical `activity_log`
entries, so deleting it outright would break that audit trail. Deletion is
still possible, but only for an account with no logged activity.

**Server-side guard, not just a disabled UI button**: it is impossible to
deactivate, demote, or delete the **last** active `super_admin` —
`User::role('super_admin')->where('is_active', true)->count() > 1` is
checked before any of those three actions succeed. Losing the last one
would mean nobody could manage admin accounts anymore.

## Dashboard

`Admin\DashboardController` (Inertia page on first load,
`GET admin/dashboard/stats?from=&to=` for period changes — a light JSON
fetch, not a full Inertia reload, to keep the period filter responsive).

`DashboardService::getStats(CarbonPeriod $period): array` is pure
aggregation (no HTTP concerns), pre-shaped for Chart.js
(`{labels: [], datasets: []}`), returning:
- **KPIs**: available cats, adoptions in period, deposit revenue in period
  (cents, `Deposit::where('status', Paid)->whereBetween('paid_at', ...)`),
  pending contact requests.
- **Charts**: adoptions by month, deposit revenue by month, cats by status
  (donut), cats by color (bar).

Adoptions-by-month reads from `spatie/laravel-model-status`'s **status
history**, not the cat's current status column — a cat that was adopted
and later released back to available (see
[payments.md](payments.md#manual-admin-recorded-reservations--a-separate-simpler-path))
must still count in the month it was actually adopted. This is the whole
reason `Cat` uses `HasStatuses` instead of a plain enum column — see
[domain-model.md](domain-model.md#cat--the-central-model).

`PeriodFilter.vue` — presets (Today/7d/30d/This month/This year) + a
custom range via PrimeVue `Calendar` in range mode.

## Activity log

`spatie/laravel-activitylog` was wired on `User` from early on (Phase 5)
but had no viewing UI until 2026-08-16 — see `Admin\ActivityLogController`
(`admin/activity-log`, `role:super_admin`, read-only — no
`password.confirm`, same tier as `settings.edit`).

Filters via `spatie/laravel-query-builder`: `log_name` (module), `event`
(created/updated/deleted), `causer_id`, `from`/`to`. The "Details" dialog
in `Admin/ActivityLog/Index.vue` renders a **field-by-field diff table**
(Field / Before / After), not raw JSON — built from `properties.attributes`
+ `properties.old` merged by key.

11 business/CMS models are logged, each with `LogsActivity` +
`useLogName(...)` (so the "Module" filter stays readable) +
`logOnly([...])` excluding translatable/long-text/secret columns —
see [domain-model.md](domain-model.md) for the per-model column list. Two
models are deliberately **not** logged: `PaymentIntentTracking` (internal
technical artifact, never seen by an admin) and `EditorUpload` (a bare
medialibrary anchor, no content of its own).

A `causer_id` of `null` means the change happened outside an
authenticated HTTP request — a Stripe webhook or `ReconcileCheckouts`
confirming a deposit, for instance. The UI shows this as "Système" rather
than trying to distinguish *which* background process it was; `log_name`
+ `event` already carry enough context.

**Retention**: `config('activitylog.delete_records_older_than_days')` is
`365`. Purged via `activitylog:clean --force`, triggered from **two**
places for the same reason described in
[architecture.md](architecture.md#the-cronrun-endpoint--why-it-exists) —
`Schedule::command(...)->monthly()` in `routes/console.php` (correct on
an environment with a real `schedule:work`/crontab) **and** a
`Cache::add()`-gated call directly inside `/cron/run` (`routes/web.php`,
30-day TTL) for the target shared-hosting environment, where
`schedule:run`'s monthly slot isn't guaranteed to align with any
particular external trigger call.

## Rich text editor (`Page.body` only)

`RichTextEditor.vue` — TipTap (`@tiptap/vue-3` + `starter-kit` +
`extension-link` + `extension-image` + `extension-placeholder`), one
instance per FR/EN tab in `Admin/Pages/Form.vue`. `v-model` is raw HTML.

Image upload: `POST admin/media/upload`
(`Admin\EditorUploadController`) → a dedicated `EditorUpload` model (no
content columns of its own, just a medialibrary anchor,
`editor-uploads` collection) → returns a URL, never base64 inline in the
content.

Server-side sanitization at save time (`mews/purifier`, `cms` profile,
`config/purifier.php`) inside `StorePageRequest`/`UpdatePageRequest`'s
`passedValidation()` — TipTap's HTML output crosses a trust boundary
before being persisted, even though today's only writer is the admin
themself.

**`Page.body`/`meta_title`/`meta_description` are excluded from the
activity log** — potentially large rich-text blobs, not useful in a diff
table. Only `slug`/`menu_group`/`order`/`is_published` are logged for
`Page`.

## Notifications (in-app + email)

Two channels per notification (`mail` + `database`), Laravel's standard
`notifications` table. `NotificationBell.vue` in `AdminLayout.vue`,
fed by the `notifications` shared Inertia prop (`unread_count` + last 10)
— **refreshed on every navigation, not polled**, a deliberate choice to
avoid an extra request loop for something this low-urgency.

`App\Notifications\Concerns\NotifiesStaff` (`activeStaff(?int $excludeUserId = null)`)
is the one shared way every notification-sending call site resolves
"which admins get notified" — `User::role(['admin', 'super_admin'])->where('is_active', true)`
— rather than each call site re-deriving that query.

| Notification | Trigger | Channels |
|---|---|---|
| `NewDepositCreatedNotification` | admin-recorded reservation created (`reserve()`) | mail + database |
| `DepositPaidNotification` | a deposit is actually confirmed paid | mail + database, staff only |
| `DepositUnavailableNotification` | lost the race (see payments.md) | mail (client + staff), database (staff) |
| `StripeReconciliationIssueNotification` | `ReconcileCheckouts` hit a Stripe error | mail + database |
| `DepositConfirmationUndeliveredNotification` | confirmation email retried past the attempt cap | mail + database |
| `NewContactRequestNotification` | public contact form submitted | mail + database |
| `NewNewsletterSubscriberNotification` | new/re-subscription | database only — too frequent/low-stakes to email every admin |
| `ContactRequestConfirmedNotification` | to the visitor, confirming receipt | mail only, client's locale |
| `NewsletterSubscriptionConfirmedNotification` | to the subscriber, with the one-click unsubscribe link | mail only, client's locale |

Routes for `admin/notifications/{notification}/read` and
`admin/notifications/read-all` live inside `routes/admin.php`'s
unprefixed-name `dashboard` group specifically — Breeze's own generated
auth code targets `route('dashboard')` by that exact name, so only the
URL moved under `/admin`, never the route name.

## Generic table filter/sort/search (`useTableQuery`)

`resources/js/Composables/useTableQuery.ts` — no knowledge of any
specific model, built once and reused across admin list pages
(`Admin/Cats/Adoption/Index.vue`, `Admin/ActivityLog/Index.vue`, and
usable by any future list). Wraps `router.get(route(...), {
preserveState: true, preserveScroll: true, replace: true })` with:
`filters` (reactive, hydrated from `filter[...]` URL params),
`sortField`/`sortOrder` (hydrated from `?sort=`), `applyFilters()`
(instant, for Select/date inputs), `applyFiltersDebounced()` (300ms via
`@vueuse/core`'s `useDebounceFn`, for free-text search), `onSort()`
(compatible with PrimeVue `DataTable`'s `@sort` event in `lazy` mode),
`goToPage()`.

Backend side: `spatie/laravel-query-builder`'s `allowedFilters()` /
`allowedSorts()` / `defaultSort()` per controller. Note `status` isn't a
real column on `cats` (it's `spatie/laravel-model-status`'s own table) —
filtering on it goes through `AllowedFilter::callback()` calling the
`HasStatuses::scopeCurrentStatus()` scope directly on a `Cat` instance
(`(new Cat)->scopeCurrentStatus($query, $value)`) rather than via
`Builder::__call()` magic forwarding, specifically to keep Larastan happy
about the query builder's generic type.
