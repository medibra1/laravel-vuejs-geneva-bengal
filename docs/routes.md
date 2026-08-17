# Route reference

This app has no separate REST/JSON API — nearly every route renders an
Inertia page (server-driven navigation, see [architecture.md](architecture.md)).
For those, "response body" means the **props passed to `Inertia::render()`**,
not an HTTP JSON payload — the two are conflated below since that's the
real contract the frontend depends on. A handful of routes return real
JSON, plain text/XML, or a redirect only — marked explicitly.

Locale prefix: every route under "Public" is registered twice by
`Route::localize()` — once at `/fr/...`, once at `/en/...`. Admin/auth
routes are never locale-prefixed (see [i18n.md](i18n.md#layer-1--routing-niels-numberslaravel-localizer)).

Source of truth is always `routes/web.php` / `routes/admin.php` plus the
controllers/Form Requests themselves — this doc is a readable index of
them, not a replacement. Two shared resources referenced repeatedly below:

- **`CatResource`** (`app/Http/Resources/CatResource.php`) — `id, slug,
  name, type, sex, color_id, second_color_id, color (whenLoaded), 
  second_color (whenLoaded), description ({fr, en}), price, birth_date,
  eye_color, available_at, diet, litter_trained, neutered, status,
  sire_litters_count (whenCounted), dam_litters_count (whenCounted),
  photos ([{id, url, sm_url, md_url, lg_url}]), deposits (whenLoaded —
  only populated on `AdoptionCatController::edit()`: array of
  `{id, status, amount, currency, payment_method, payment_link_url,
  paid_at, finalized_at, owner}`)`.
- **`GalleryResource`** (`app/Http/Resources/GalleryResource.php`) —
  `id, caption, position, type, image_url, image_sm_url, image_md_url,
  image_lg_url`.

Quick index first, full detail (Form Request rules, exact props, business
guards) in the sections below.

## Quick index — Public

| Method | Path | Name | Controller@action |
|---|---|---|---|
| GET | `/` | `home` | `HomeController@index` |
| GET | `/chatons-disponibles` | `cats.index` | `CatController@index` |
| GET | `/chatons-disponibles/couleur/{color:slug}` | `cats.index.color` | `CatController@filterByColor` |
| GET | `/chatons-disponibles/{cat:slug}` | `cats.show` | `CatController@show` |
| GET | `/nos-chats-reproducteurs` | `cats.breeders` | `CatController@breeders` |
| GET | `/portees-prevues` | `litters.index` | `LitterController@index` |
| GET | `/galerie` | `galleries.index` | `GalleryController@index` |
| GET | `/a-propos` | `pages.a-propos` | `PageController@show` (slug fixed to `a-propos`) |
| GET | `/contact` | `pages.contact` | `PageController@show` (slug fixed to `contact`) |
| GET | `/pages/{slug}` | `pages.show` | `PageController@show` — generic CMS pages |
| POST | `/contact` | `contact.store` | `ContactController@store` — honeypot-protected |
| POST | `/newsletter` | `newsletter.store` | `NewsletterController@store` — honeypot-protected |
| GET | `/newsletter/unsubscribe/{token}` | `newsletter.unsubscribe` | `NewsletterController@unsubscribe` |
| POST | `/deposits` | `deposits.store` | `DepositController@store` — form only, no Stripe call |
| GET | `/deposits` | *(unnamed)* | redirects to `cats.index` |
| POST | `/deposits/confirm-intent` | `deposits.confirm-intent` | `DepositController@confirmIntent` — **JSON** |
| GET | `/deposits/return/{paymentIntentId}` | `deposits.return` | `DepositController@show` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` — `auth` only |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` — `auth` only |

## Quick index — not locale-prefixed

| Method | Path | Name | Notes |
|---|---|---|---|
| POST | `/webhooks/stripe` | `webhooks.stripe` | CSRF-exempt, Stripe signature verified instead |
| GET | `/cron/run` | `cron.run` | token-gated, throttled `10,1` |
| GET | `/sitemap.xml` | `sitemap` | XML, cached 1h |

## Quick index — Admin (`role:admin|super_admin`)

| Method | Path | Name |
|---|---|---|
| GET | `admin/dashboard` | `dashboard` |
| GET | `admin/dashboard/stats` | `dashboard.stats` — **JSON** |
| POST | `admin/notifications/{notification}/read` | `admin.notifications.read` |
| POST | `admin/notifications/read-all` | `admin.notifications.read-all` |
| resource | `admin/cats/adoption` (except `show`) | `admin.cats.adoption.*` |
| DELETE | `admin/cats/adoption/{cat}/photos/{media}` | `admin.cats.adoption.photos.destroy` |
| resource | `admin/cats/breeders` (except `show`) | `admin.cats.breeders.*` |
| DELETE | `admin/cats/breeders/{cat}/photos/{media}` | `admin.cats.breeders.photos.destroy` |
| resource | `admin/owners` (except `show`) | `admin.owners.*` |
| resource | `admin/litters` (except `show`) | `admin.litters.*` |
| resource | `admin/galleries` (except `show`) | `admin.galleries.*` |
| resource | `admin/pages` (except `show`) | `admin.pages.*` |
| POST | `admin/media/upload` | `admin.media.upload` — **JSON** |
| resource | `admin/faq-items` (except `show`) | `admin.faq-items.*` |
| resource | `admin/testimonials` (except `show`) | `admin.testimonials.*` |
| resource | `admin/contact-requests` (`index`/`update`/`destroy`) | `admin.contact-requests.*` |
| GET | `admin/newsletter-subscribers` | `admin.newsletter-subscribers.index` |
| GET | `admin/newsletter-subscribers/export` | `admin.newsletter-subscribers.export` — CSV download |
| PATCH | `admin/newsletter-subscribers/{id}/toggle-unsubscribed` | `admin.newsletter-subscribers.toggle-unsubscribed` |
| GET | `admin/deposits` | `admin.deposits.index` |
| GET | `admin/deposits/create` | `admin.deposits.create` |
| POST | `admin/deposits` | `admin.deposits.store` |
| POST | `admin/deposits/{deposit}/mark-paid` | `admin.deposits.mark-paid` |
| POST | `admin/deposits/{deposit}/verify-stripe` | `admin.deposits.verify-stripe` — `password.confirm` |
| POST | `admin/deposits/{deposit}/finalize` | `admin.deposits.finalize` — `password.confirm` |
| POST | `admin/deposits/{deposit}/assign-cat` | `admin.deposits.assign-cat` |

## Quick index — Admin (`role:super_admin` only)

| Method | Path | Name |
|---|---|---|
| GET | `admin/settings` | `admin.settings.edit` |
| PUT | `admin/settings` | `admin.settings.update` |
| GET | `admin/activity-log` | `admin.activity-log.index` — read-only, no `password.confirm` |
| resource | `admin/users` (except `show`) | `admin.users.*` — `store`/`update`/`destroy` require `password.confirm` |
| POST | `admin/users/{user}/resend-reset-link` | `admin.users.resend-reset-link` — `password.confirm` |
| PATCH | `admin/users/{user}/toggle-active` | `admin.users.toggle-active` — `password.confirm` |
| POST | `admin/deposits/{deposit}/refund` | `admin.deposits.refund` — `password.confirm` |
| POST | `admin/deposits/{deposit}/cancel` | `admin.deposits.cancel` — `password.confirm` |
| POST | `admin/cats/finalize-directly` | `admin.cats.finalize-directly` — `password.confirm` |

See [architecture.md](architecture.md#roles--permissions-authorization-layers)
for why these three tiers exist and how `password.confirm` fits in.

---

# Full detail, by controller

## Public — `app/Http/Controllers/Public/`

### `HomeController`
**index** — GET `/` (`home`). No validation. → `Public/Home`:
`seo {title, description}` (from `SiteSetting`, hardcoded fallback),
`heroSlides` (`GalleryResource[]`, type=hero_slide), `socialTiles`
(`GalleryResource[]`, type=social_tile).

### `CatController`
**index** — GET `/chatons-disponibles` (`cats.index`). No validation. →
`Public/ChatonsDisponibles`: `cats` (`CatResource[]`, kittens/cats with
`status === disponible`), `activeColorSlug` (`null`), `heroSlides`.

**filterByColor** — GET `/chatons-disponibles/couleur/{color:slug}`
(`cats.index.color`). Route-model-bound `Color` by slug, 404 on unknown.
Same as `index`, additionally filtered to `color_id = $color->id OR
second_color_id = $color->id`. `activeColorSlug` = the color's slug.

**show** — GET `/chatons-disponibles/{cat:slug}` (`cats.show`).
Route-model-bound `Cat` by slug. → `Public/ChatonDetail`: `cat`
(`CatResource`), `depositAmount` (int, `SiteSetting::get('deposit_amount',
50000)`), `heroSlides`.

**breeders** — GET `/nos-chats-reproducteurs` (`cats.breeders`). No
status/price filtering (not applicable to breeders). → `Public/Reproducteurs`:
`cats` (`CatResource[]`, all `type = Breeder`), `heroSlides`.

### `LitterController`
**index** — GET `/portees-prevues` (`litters.index`). No validation. →
`Public/PorteesPrevues`: `litters` (array of `{id, expected_date (Y-m-d),
notes, sire, dam}`, each parent a `{id, slug, name, color, photo_url}|null`
— future litters only, ascending), `heroSlides`.

### `GalleryController`
**index** — GET `/galerie` (`galleries.index`). No validation. →
`Public/Galerie`: `galleries` (`GalleryResource[]`, type=gallery, ordered
by position), `heroSlides`.

### `PageController`
**show** — GET `/a-propos` (`pages.a-propos`, slug fixed), GET `/contact`
(`pages.contact`, slug fixed), GET `/pages/{slug}` (`pages.show`). No Form
Request — plain `$slug`. `Page::where('slug', $slug)->firstOrFail()` (404
if missing), `abort_if(!is_published, 404)`. → `Public/Page`: `page`
(`{slug, title, body, meta_title, meta_description}`, current-locale
strings), plus conditionally: `testimonials` (if slug `a-propos`, array of
`{id, author_name, quote, rating}`, published only), `prefilledCat` (if
slug `contact`, `{id, name}|null` from `?chaton=slug`), `faqItems` (if
slug `faq`, array of `{id, question, answer}`).

### `ContactController`
**store** — POST `/contact` (`contact.store`). Middleware:
`ProtectAgainstSpam`. Request: `Public\StoreContactRequestRequest` —
`name` (required, string, max:255), `email` (required, email, max:255),
`reason` (required, `Rule::enum(ContactReason::class)`), `cat_id`
(nullable, exists:cats,id), `city` (nullable, string, max:255), `message`
(required, string, max:5000). Creates `ContactRequest`, notifies active
staff + sends the visitor a locale-pinned confirmation email. → plain
`redirect()->back()->with('success', ...)`, not an Inertia render.

### `NewsletterController`
**store** — POST `/newsletter` (`newsletter.store`). Middleware:
`ProtectAgainstSpam`. Request: `Public\StoreNewsletterSubscriberRequest`
— `email` (required, email, max:255). `firstOrNew` by email; on new/
re-subscription sets `unsubscribe_token`, clears `unsubscribed_at`,
notifies staff + sends a locale-pinned confirmation email. → `back()->with('success', ...)`.

**unsubscribe** — GET `/newsletter/unsubscribe/{token}`
(`newsletter.unsubscribe`). No validation. Looks up by
`unsubscribe_token`; if found and not already unsubscribed, sets
`unsubscribed_at = now()`. → `Public/NewsletterUnsubscribed`: `found`
(bool) — same page renders regardless of match.

### `DepositController`
**store** — POST `/deposits` (`deposits.store`). Middleware:
`ProtectAgainstSpam`. (GET `/deposits` is an inline closure redirecting to
`cats.index` — no controller, no form data to render otherwise.) Request:
`Public\StoreDepositRequest` — `name` (required, string, max:255), `email`
(required, email, max:255), `phone` (nullable, string, max:50), `cat_id`
(nullable, exists:cats,id, custom rule `CatIsAvailableForDeposit`). → no
Stripe call yet — `Public/DepositPay`: `catId`, `catName`, `catSlug`
(all nullable), `amount` (int, `SiteSetting deposit_amount`, default
50000), `currency` (`"CHF"`), `stripePublishableKey`
(`config('services.stripe.key')`), `name`/`email`/`phone` (echoed back).

**confirmIntent** — POST `/deposits/confirm-intent`
(`deposits.confirm-intent`). Middleware: `ProtectAgainstSpam`. Request:
`Public\ConfirmPaymentIntentRequest` — same shape as `StoreDepositRequest`.
Guard: if `cat_id` set and `Deposit::blocksNewReservation($catId)` →
`ValidationException` on `cat_id` ("This kitten has already been
reserved."). On pass: creates the Stripe PaymentIntent, writes a
`PaymentIntentTracking` row. → **JSON**: `{paymentIntentId, clientSecret}`.
See [payments.md](payments.md).

**show** — GET `/deposits/return/{paymentIntentId}` (`deposits.return`).
No validation. `Deposit::where('provider_reference', $paymentIntentId)->first()`.
→ `Public/DepositReturn`: `depositStatus` (`DepositStatus` value, `Pending`
if no `Deposit` row exists yet), `email` (string|null, only once paid).

### `SitemapController`
**index** — GET `/sitemap.xml` (`sitemap`, not locale-prefixed). No
validation. Builds one XML sitemap covering every locale/hreflang variant
of every static path, every kitten/breeder cat, every published CMS page.
Cached 1h (`Cache::remember`). → raw `response($xml, 200, ['Content-Type'
=> 'text/xml'])` — not Inertia/JSON. See [seo.md](seo.md).

### `StripeWebhookController`
**handle** — POST `/webhooks/stripe` (`webhooks.stripe`, not
locale-prefixed, CSRF-exempt). No Form Request — raw `Request` passed to
`PaymentGateway::handleWebhook()` for signature verification. Idempotency:
no-ops (204) if unhandled, no PaymentIntent id, or a `Deposit` with that
`provider_reference` already exists. Otherwise
`DepositPaymentProcessor::createFromPayment(...)`. → always
`response()->noContent()` (204, no body). See [payments.md](payments.md).

### `ProfileController` (`app/Http/Controllers/ProfileController.php`)
**edit** — GET `/profile` (`profile.edit`). Middleware: `auth` only (no
`verified`/role). → `Profile/Edit`: `mustVerifyEmail` (bool),
`status` (`session('status')`).

**update** — PATCH `/profile` (`profile.update`). Middleware: `auth`.
Request: `ProfileUpdateRequest` — `name` (required, string, max:255),
`email` (required, email, max:255, unique ignoring current user). Resets
`email_verified_at` if the email changed. → `Redirect::route('profile.edit')`.

Auth scaffolding (`login`, `register`, password reset, email verification)
is stock Laravel Breeze under `app/Http/Controllers/Auth/`, unmodified —
not detailed here.

### `GET /cron/run` (inline closure, `routes/web.php`, not locale-prefixed)
No controller. Middleware: `throttle:10,1`. Reads `?token=` directly,
`abort_unless(hash_equals(config('app.cron_secret'), $token), 403)`. Runs
`schedule:run`, `queue:work --stop-when-empty --max-time=50`,
unconditionally `dispatch_sync(new ReconcileCheckouts)` (idempotent), and
`activitylog:clean --force` gated by a 30-day `Cache::add()` claim. →
plain text `response('OK', 200)`. See [architecture.md](architecture.md#the-cronrun-endpoint--why-it-exists).

## Admin — `app/Http/Controllers/Admin/`

Base middleware unless noted: `auth`, `verified`, `role:admin|super_admin`.
Three super_admin-only controllers/actions are marked explicitly.

### `DashboardController`
**index** — GET `admin/dashboard` (`dashboard` — kept unprefixed, Breeze's
generated code targets this exact name). Inline validation (`from`/`to`:
nullable, date) in a private `resolvePeriod()`; defaults to
start-of-month → today, swaps if reversed. → `Admin/Dashboard`: `stats`
(`DashboardService::getStats()` shape, see [admin.md](admin.md#dashboard)),
`period` (`{from, to}`).

**stats** — GET `admin/dashboard/stats` (`dashboard.stats`). Same
validation/defaults as `index`. → **JSON**: `{stats, period}` — background
fetch for the period filter, not a page render.

### `NotificationController`
**read** — POST `admin/notifications/{notification}/read`. No Form
Request; `$notification` is a raw id, looked up via
`$request->user()->notifications()->find($id)` (scoped to the current
user — not a global route-model binding, so one admin can't mark another's
notification read by guessing an id). `abort_unless(found, 404)`. →
`back()`.

**readAll** — POST `admin/notifications/read-all`. No validation.
`$request->user()->unreadNotifications->markAsRead()`. → `back()`.

### `Cats\AdoptionCatController`
`Route::resource('cats/adoption', ...)->except('show')` + one extra route.
Every action guards `ensureAdoptionType($cat)` (`abort_unless(type in
[Kitten, Cat], 404)`) — see [architecture.md](architecture.md#roles--permissions-authorization-layers)
for why route-model-binding alone can't isolate this from the breeder
section.

**index** — GET `admin/cats/adoption`. `spatie/laravel-query-builder`
filters: `name` (partial), `type` (exact), `color_id` (exact), `status`
(callback via `HasStatuses`), `search` (callback, `name` OR `eye_color`
LIKE); sorts `name, created_at, price, birth_date` (default
`-created_at`); scoped to `type IN [Kitten, Cat]`. → `Admin/Cats/Adoption/Index`:
`cats` (paginated 20, `CatResource`), `colors` (`{id, name, hex_code}[]`).

**create** — GET `admin/cats/adoption/create`. → `Admin/Cats/Adoption/Form`:
`colors`.

**store** — POST `admin/cats/adoption`. Request:
`Admin\Cats\StoreAdoptionCatRequest` — `name` (required, string, max:255),
`sex` (required, `Rule::enum(CatSex::class)`), `color_id` (required,
exists:colors,id), `second_color_id` (nullable, different:color_id,
exists:colors,id), `description.fr`/`description.en` (nullable, string),
`price` (nullable, integer, min:0), `birth_date`/`available_at` (nullable,
date), `eye_color`/`diet` (nullable, string, max:255), `litter_trained`/
`neutered` (boolean), `status` (nullable, `Rule::enum(CatStatus::class)`),
`photos` (nullable array; each: image, max:5120 KB). Forces `type =
Kitten` server-side (never form input). → `redirect(admin.cats.adoption.index)`.

**edit** — GET `admin/cats/adoption/{cat}/edit`. →
`Admin/Cats/Adoption/Form`: `cat` (`CatResource`, with color/secondColor/
media/statuses/**deposits** loaded — the one place `CatResource.deposits`
is populated), `colors`, `owners` (`{id, first_name, last_name, email,
phone}[]`, all, ordered by last_name).

**update** — PUT/PATCH `admin/cats/adoption/{cat}`. Request:
`Admin\Cats\UpdateAdoptionCatRequest` — same as Store, `status` instead
uses `Rule::in([Available, Pending, Adopted])` + a closure rejecting a
transition *to* `Adopted` unless already `Adopted` (adoption can only
happen via `DepositPaymentProcessor::finalize()`, never through this
form). → `redirect(admin.cats.adoption.index)`.

**destroy** — DELETE `admin/cats/adoption/{cat}`. → `redirect(admin.cats.adoption.index)`.

**destroyPhoto** — DELETE `admin/cats/adoption/{cat}/photos/{media}`.
Additional guard: `abort_unless($media->model_type === Cat::class &&
$media->model_id === $cat->id, 404)` — prevents deleting another model's
media by guessing an id. → `back()`.

### `Cats\BreederCatController`
Mirror of `AdoptionCatController`, guarded by `ensureBreederType($cat)`
instead. No price/status/available_at fields anywhere in this controller.

**index** — GET `admin/cats/breeders`. Filters: `name` (partial),
`color_id` (exact), `search` (callback); sorts `name, created_at,
birth_date` (default `-created_at`); `withCount(['sireLitters',
'damLitters'])`. → `Admin/Cats/Breeders/Index`: `cats` (paginated 20,
`CatResource`, with litter counts populated).

**create** — GET `admin/cats/breeders/create`. → `Admin/Cats/Breeders/Form`:
`colors`.

**store** — POST `admin/cats/breeders`. Request:
`Admin\Cats\StoreBreederCatRequest` — same as adoption's minus `price`/
`available_at`/`status`. Forces `type = Breeder` server-side. →
`redirect(admin.cats.breeders.index)`.

**edit** — GET `admin/cats/breeders/{cat}/edit`. → `cat` (`CatResource`,
color/secondColor/media — no deposits/statuses), `colors`.

**update** — Request: `Admin\Cats\UpdateBreederCatRequest`, same rules as
Store. → `redirect(admin.cats.breeders.index)`.

**destroy** / **destroyPhoto** — same shape as the adoption controller's.

### `OwnerController`
`Route::resource('owners', ...)->except('show')`. No Resource class — raw
Eloquent models throughout.

**index** — GET `admin/owners`. → `Admin/Owners/Index`: `owners`
(paginated 20, `desiredCat:{id,name}`/`desiredColor:{id,name}` eager-loaded).

**create** — GET `admin/owners/create`. → `Admin/Owners/Form`: `cats`
(`{id,name}[]`, adoptable — excludes already-`Adopted`), `colors`
(`{id,name}[]`).

**store** — POST `admin/owners`. Request: `Admin\StoreOwnerRequest` —
`first_name`/`last_name` (required, string, max:255), `email` (required,
email, max:255, unique:owners,email), `phone` (nullable, string, max:50),
`city` (nullable, string, max:255), `desired_cat_id` (nullable,
exists:cats,id), `desired_color_id` (nullable, exists:colors,id). →
`redirect(admin.owners.index)`.

**edit** — GET `admin/owners/{owner}/edit`. → `owner` (with
desiredCat/desiredColor loaded), `cats`, `colors`.

**update** — Request: `Admin\UpdateOwnerRequest` — same as Store, `email`
uniqueness ignores the current owner. → `redirect(admin.owners.index)`.

**destroy** — DELETE `admin/owners/{owner}`. → `redirect(admin.owners.index)`.

### `LitterController`
`Route::resource('litters', ...)->except('show')`.

**index** — GET `admin/litters`. → `Admin/Litters/Index`: `litters`
(paginated 20, `sire`/`dam` loaded, `kittens_count`).

**create** — GET `admin/litters/create`. → `sires` (`{id,name}[]`,
sex=Male), `dams` (`{id,name}[]`, sex=Female).

**store** — POST `admin/litters`. Request: `Admin\StoreLitterRequest` —
`sire_cat_id` (nullable, different:dam_cat_id, exists:cats,id),
`dam_cat_id` (nullable, exists:cats,id), `expected_date` (nullable, date),
`notes` (nullable, string). → `redirect(admin.litters.index)`.

**edit** — GET `admin/litters/{litter}/edit`. → `litter` (sire/dam
loaded), `sires`, `dams`.

**update** — Request: `Admin\UpdateLitterRequest`, identical rules. →
`redirect(admin.litters.index)`.

**destroy** — DELETE `admin/litters/{litter}`. → `redirect(admin.litters.index)`.

### `GalleryController`
`Route::resource('galleries', ...)->except('show')`. `type` (query
string, resolved via `GalleryType::tryFrom() ?? Gallery`) scopes every
action to one gallery type at a time.

**index** — GET `admin/galleries`. → `Admin/Galleries/Index`: `galleries`
(paginated 20, `GalleryResource`), `type`.

**create** — GET `admin/galleries/create`. → `type`.

**store** — POST `admin/galleries`. Request: `Admin\StoreGalleryRequest`
(`prepareForValidation` defaults `type`→Gallery, `position`→0) —
`caption` (nullable, string, max:255), `position` (required, integer,
min:0, unique **scoped per type**), `image` (required, image, max:5120),
`type` (required, enum, custom rule `GalleryTypeLimitNotReached`). →
`redirect(admin.galleries.index, {type})`.

**edit** — GET `admin/galleries/{gallery}/edit`. → `gallery`
(`GalleryResource`, media loaded), `type`.

**update** — Request: `Admin\UpdateGalleryRequest` (defaults from the
gallery's current values) — `caption`, `position` (unique per type,
ignoring current row), `image` (nullable — no re-upload required), `type`
(required, enum — no `GalleryTypeLimitNotReached` check on update). →
`redirect(admin.galleries.index, {type})`.

**destroy** — DELETE `admin/galleries/{gallery}`. → `redirect(admin.galleries.index, {type})`.

### `PageController`
`Route::resource('pages', ...)->except('show')`.

**index** — GET `admin/pages`. → `Admin/Pages/Index`: `pages` (paginated
20, ordered by menu_group/order).

**create** — GET `admin/pages/create`. No props.

**store** — POST `admin/pages`. Request: `Admin\StorePageRequest`
(`prepareForValidation` runs `mews/purifier`'s `cms` profile over
`body.fr`/`body.en`) — `menu_group` (nullable, string, max:255), `order`
(nullable, integer, min:0), `title.fr`/`title.en` (required, string,
max:255), `body.fr`/`body.en` (nullable, string), `meta_title.fr`/`.en`
(nullable, string, max:255), `meta_description.fr`/`.en` (nullable,
string, max:500), `is_published` (boolean). → `redirect(admin.pages.index)`.

**edit** — GET `admin/pages/{page}/edit`. → `page` (`{id, slug,
menu_group, order, title, body, meta_title, meta_description,
is_published}`, both locales).

**update** — Request: `Admin\UpdatePageRequest`, identical rules/
sanitization. → `redirect(admin.pages.index)`.

**destroy** — DELETE `admin/pages/{page}`. → `redirect(admin.pages.index)`.

### `EditorUploadController`
**store** — POST `admin/media/upload`. Request:
`Admin\StoreEditorUploadRequest` — `image` (required, image, max:5120).
Creates a blank `EditorUpload`, attaches to `editor-uploads` collection. →
**JSON**: `{url}` — consumed by `RichTextEditor.vue`'s image button, not a
page. See [admin.md](admin.md#rich-text-editor).

### `FaqItemController`
`Route::resource('faq-items', ...)->except('show')`.

**index** — GET `admin/faq-items`. → `faqItems` (paginated 20, ordered by
`order`).

**create** — GET `admin/faq-items/create`. No props.

**store** — POST `admin/faq-items`. Request: `Admin\StoreFaqItemRequest`
— `question.fr`/`.en` (required, string, max:255), `answer.fr`/`.en`
(required, string), `order` (nullable, integer, min:0). →
`redirect(admin.faq-items.index)`.

**edit** — GET `admin/faq-items/{faqItem}/edit`. → `faqItem`.

**update** — Request: `Admin\UpdateFaqItemRequest`, identical rules. →
`redirect(admin.faq-items.index)`.

**destroy** — DELETE `admin/faq-items/{faqItem}`. → `redirect(admin.faq-items.index)`.

### `TestimonialController`
`Route::resource('testimonials', ...)->except('show')`.

**index** — GET `admin/testimonials`. → `testimonials` (paginated 20,
ordered by `order`).

**create** — GET `admin/testimonials/create`. No props.

**store** — POST `admin/testimonials`. Request:
`Admin\StoreTestimonialRequest` — `author_name` (required, string,
max:255), `quote.fr`/`.en` (required, string), `rating` (nullable,
integer, 1-5), `is_published` (boolean), `order` (nullable, integer,
min:0). → `redirect(admin.testimonials.index)`.

**edit** — GET `admin/testimonials/{testimonial}/edit`. → `testimonial`.

**update** — Request: `Admin\UpdateTestimonialRequest`, identical rules.
→ `redirect(admin.testimonials.index)`.

**destroy** — DELETE `admin/testimonials/{testimonial}`. →
`redirect(admin.testimonials.index)`.

### `ContactRequestController`
`Route::resource('contact-requests', ...)->only(['index','update','destroy'])`.

**index** — GET `admin/contact-requests`. → `contactRequests` (paginated
20, `cat:{id,name}` eager-loaded).

**update** — PUT/PATCH `admin/contact-requests/{contactRequest}`.
Request: `Admin\UpdateContactRequestRequest` — `status` (required,
`Rule::enum(ContactStatus::class)`). → `redirect(admin.contact-requests.index)`.

**destroy** — DELETE `admin/contact-requests/{contactRequest}`. →
`redirect(admin.contact-requests.index)`.

### `NewsletterSubscriberController`
Not a resource — 3 explicit routes.

**index** — GET `admin/newsletter-subscribers`. → `subscribers`
(paginated 20, latest first).

**export** — GET `admin/newsletter-subscribers/export`. No validation. →
`response()->streamDownload(...)`: CSV, `;`-separated + UTF-8 BOM, columns
`E-mail, Statut, Inscrit le, Désabonné le` — not Inertia/JSON.

**toggleUnsubscribed** — PATCH
`admin/newsletter-subscribers/{id}/toggle-unsubscribed`. No Form Request.
Toggles `unsubscribed_at` between `now()`/`null`. → `back()`.

### `DepositController`
Explicit routes, not a resource. See [payments.md](payments.md) for the
processor calls referenced below.

**index** — GET `admin/deposits`. Filters: `status` (exact), `cat_id`
(exact), `from`/`to` (callback date range), `waiting_list` (callback,
`cat_id IS NULL`). → `Admin/Deposits/Index`: `deposits` (paginated 20,
`cat:{id,name}`/`owner:{id,first_name,last_name}` eager-loaded), `cats`
(`{id,name}[]`, all), `owners` (`{id, first_name, last_name, email,
phone}[]`, all), `reservableCats` (`{id,name}[]`, adoption-type &
non-adopted only).

**create** — GET `admin/deposits/create`. → `cats` (reservable options),
`owners`, `defaultAmount` (int, `SiteSetting deposit_amount`).

**store** — POST `admin/deposits`. Request: `Admin\StoreDepositRequest` —
`cat_id` (nullable, exists:cats,id, `CatIsAvailableForDeposit`), `name`/
`email` (`required_without:new_owner`, nullable otherwise), `phone`
(nullable), `amount` (nullable, integer, min:0), `payment_method`
(nullable, `Rule::in(['cash','bank_transfer','twint_manual'])` — Stripe
excluded, see payments.md), `owner_id` (nullable, exists:owners,id),
`new_owner.*` (`first_name`/`last_name`/`email` required_with:new_owner,
`email` unique:owners,email, `phone`/`city` nullable). Resolves owner
(`owner_id` wins over `new_owner`), creates `Deposit` (status `Pending`,
`provider` mirrors `payment_method`, `created_by = current admin`), calls
`DepositPaymentProcessor::reserve($deposit)`. → `redirect(admin.deposits.index)`.

**markPaid** — POST `admin/deposits/{deposit}/mark-paid`. Request:
`Admin\MarkDepositPaidRequest` — `payment_method`
(`Rule::requiredIf($deposit->payment_method === null)`, nullable,
`Rule::in([...])`). Guards: rejects if `payment_method === Stripe` or
`status !== Pending` (→ `back()->with('error', ...)`). If the deposit had
no method yet, sets it before calling `DepositPaymentProcessor::markPaid()`.
→ `back()->with('success'|'error', ...)`.

**verifyStripe** — POST `admin/deposits/{deposit}/verify-stripe`. `+
password.confirm`. No Form Request. Guards: rejects if `payment_method !==
Stripe`, `status !== Pending`, or `PaymentGateway::isCheckoutPaid()` is
false. On pass: `DepositPaymentProcessor::markPaid($deposit,
$deposit->provider_reference)`. → `back()->with('success'|'error', ...)`.

**finalize** — POST `admin/deposits/{deposit}/finalize`. `+
password.confirm`. Request: `Admin\FinalizeDepositRequest` — same
`owner_id`/`new_owner.*` shape as Store. Guards: rejects if `status !==
Paid`, `finalized_at !== null`, or resolved owner is null. Calls
`DepositPaymentProcessor::finalize($deposit, $owner)` — this is what
actually moves the cat to `adopte`. → `back()->with('success'|'error', ...)`.

**assignCat** — POST `admin/deposits/{deposit}/assign-cat`. Request:
`Admin\AssignCatToDepositRequest` — `cat_id` (required,
`Rule::exists('cats','id')->whereIn('type', [Kitten, Cat])`,
`CatIsAvailableForDeposit`). Guards: rejects if `deposit->cat_id !== null`
or `status !== Pending`. Sets `cat_id`, calls
`DepositPaymentProcessor::reserve($deposit, notifyStaff: false)`. →
`back()->with('success'|'error', ...)`.

### `SiteSettingController` — `role:super_admin` only
**edit** — GET `admin/settings`. No validation. → `Admin/Settings/Edit`:
`settings` (`{key: value}` map of the 12 fixed keys — social links,
address/phone/email, deposit amount, price range, default SEO text),
`logoUrl` (string|null).

**update** — PUT `admin/settings`. Request:
`Admin\UpdateSiteSettingsRequest` — `social_facebook`/`social_instagram`/
`social_youtube`/`social_tiktok` (nullable, url, max:255), `address`
(nullable, string, max:500), `phone` (nullable, string, max:50), `email`
(nullable, email, max:255), `deposit_amount`/`price_range_min` (nullable,
integer, min:0), `price_range_max` (nullable, integer, min:0,
gte:price_range_min), `default_seo_title` (nullable, string, max:255),
`default_seo_description` (nullable, string, max:500), `logo` (nullable,
image, max:2048). Persists via `SiteSetting::set()`, explicit `(int)` cast
on the three numeric keys (see [domain-model.md](domain-model.md#cms-content--page-faqitem-testimonial-sitesetting)
for the bug this guards against). → `redirect(admin.settings.edit)`.

### `ActivityLogController` — `role:super_admin` only
**index** — GET `admin/activity-log`. Read-only, no `password.confirm`.
Filters: `log_name`/`event`/`causer_id` (exact), `from`/`to` (callback
date range). → `Admin/ActivityLog/Index`: `activities` (paginated 20,
`Spatie\Activitylog\Models\Activity`, `causer:{id,name,email}` loaded),
`logNames`/`events` (distinct values), `causers` (`{id,name}[]`, all
users). See [admin.md](admin.md#activity-log).

### `UserController` — `role:super_admin` only
`Route::resource('users', ...)->except('show')`, `store`/`update`/
`destroy` require `password.confirm`.

**index** — GET `admin/users`. → `Admin/Users/Index`: `users` (`{id,
name, email, role, is_active, last_login_at}[]`, admin|super_admin roles
only).

**create** — GET `admin/users/create`. No `password.confirm` (just a
form). No props.

**store** — POST `admin/users`. `+ password.confirm`. Request:
`Admin\StoreUserRequest` — `name` (required, string, max:255), `email`
(required, email, max:255, unique:users,email), `role` (required,
`Rule::in(['admin','super_admin'])`). Creates the user with a random
unusable password, `is_active = true`, assigns the role, sends a Laravel
password reset link. → `redirect(admin.users.index)`.

**edit** — GET `admin/users/{user}/edit`. No `password.confirm`. → `user`
(`{id, name, email, role}`).

**update** — PUT/PATCH `admin/users/{user}`. `+ password.confirm`.
Request: `Admin\UpdateUserRequest` — `role` (required, `Rule::in([...])`).
Guard: rejects (→ `back()->with('error', ...)`) demoting the **last active
super_admin**. Otherwise `syncRoles([$newRole])`. →
`redirect(admin.users.index)` or the error `back()`.

**resendResetLink** — POST `admin/users/{user}/resend-reset-link`. `+
password.confirm`. No Form Request. `Password::sendResetLink(...)`. →
`back()`.

**toggleActive** — PATCH `admin/users/{user}/toggle-active`. `+
password.confirm`. Guard: rejects deactivating the last active
super_admin. → `back()->with('success'|'error', ...)`.

**destroy** — DELETE `admin/users/{user}`. `+ password.confirm`. Guards,
in order: last active super_admin → error; has any logged `Activity` as
causer → error ("désactivez-le plutôt"); else hard-delete. →
`redirect(admin.users.index)` or `back()->with('error', ...)`.

### `DepositController` — super_admin-only actions
**refund** — POST `admin/deposits/{deposit}/refund`. `role:super_admin`
+ `password.confirm`. No Form Request. Guards: rejects if `status !==
Paid`; rejects if `PaymentGateway::refund()` returns false. On success:
`status = Refunded`. → `back()->with('success'|'error', ...)`.

**cancel** — POST `admin/deposits/{deposit}/cancel`. Same middleware.
Guard: rejects if `status !== Paid`. Calls
`DepositPaymentProcessor::cancel($deposit)` — releases the cat to
`disponible`, `status = Cancelled`, never touches money. →
`back()->with('success'|'error', ...)`.

**finalizeDirectly** — POST `admin/cats/finalize-directly`. Same
middleware. Request: `Admin\FinalizeCatDirectlyRequest` — `cat_id`
(required, exists:cats,id), `owner_id`/`new_owner` (one
`required_without` the other, same `new_owner.*` shape as elsewhere).
Guards: rejects if the cat is already `Adopted`; rejects if resolved owner
is null. Calls `DepositPaymentProcessor::finalizeDirectly($cat, $owner)` —
creates a `Deposit` under the hood (see
[payments.md](payments.md#manual-admin-recorded-reservations--a-separate-simpler-path))
even though this bypasses the normal flow, so it stays traceable. →
`back()->with('success'|'error', ...)`.
