# Route reference

This app has no separate REST/JSON API — nearly every route renders an
Inertia page (server-driven navigation, see [architecture.md](architecture.md)).
The exceptions are marked **JSON** below. Source of truth is always
`routes/web.php` / `routes/admin.php` — this table is a readable index of
it, not a replacement.

Locale prefix: every route under "Public" is registered twice by
`Route::localize()` — once at `/fr/...`, once at `/en/...`. Admin/auth
routes are never locale-prefixed (see [i18n.md](i18n.md#layer-1--routing-niels-numberslaravel-localizer)).

## Public

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
| GET | `/pages/{slug}` | `pages.show` | `PageController@show` — generic CMS pages (race info, adoption steps, FAQ...) |
| POST | `/contact` | `contact.store` | `ContactController@store` — honeypot-protected |
| POST | `/newsletter` | `newsletter.store` | `NewsletterController@store` — honeypot-protected |
| GET | `/newsletter/unsubscribe/{token}` | `newsletter.unsubscribe` | `NewsletterController@unsubscribe` — always renders the same confirmation page, valid or not |
| POST | `/deposits` | `deposits.store` | `DepositController@store` — renders the checkout form only, **no Stripe call** |
| GET | `/deposits` | *(unnamed)* | redirects to `cats.index` — a bare reload has no form data to work with |
| POST | `/deposits/confirm-intent` | `deposits.confirm-intent` | `DepositController@confirmIntent` — **JSON**, creates the Stripe PaymentIntent, called at the "Pay" click |
| GET | `/deposits/return/{paymentIntentId}` | `deposits.return` | `DepositController@show` — polled by the frontend until the webhook resolves |

## Not locale-prefixed

| Method | Path | Name | Notes |
|---|---|---|---|
| POST | `/webhooks/stripe` | `webhooks.stripe` | `StripeWebhookController@handle` — CSRF-exempt (see `bootstrap/app.php`), authenticated by Stripe signature verification instead |
| GET | `/cron/run` | `cron.run` | Token-gated (`hash_equals`), throttled `10,1` — see [architecture.md](architecture.md#the-cronrun-endpoint--why-it-exists) |
| GET | `/sitemap.xml` | `sitemap` | `SitemapController@index` — cached 1h |

## Auth (Breeze scaffolding, `routes/auth.php`)

Standard Breeze routes, unmodified: `login`, `logout`, `register`,
`password.request`, `password.email`, `password.reset`, `password.update`,
`password.confirm`, `verification.notice`, `verification.verify`,
`verification.send`. `/profile` (`profile.edit`/`profile.update`) is
defined in `routes/web.php` directly.

## Admin — `role:admin|super_admin`

All prefixed `/admin`, none locale-prefixed. `admin` (bare) redirects to
`dashboard`.

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `admin/dashboard` | `dashboard` | kept at this unprefixed name — Breeze's own generated auth code targets `route('dashboard')` |
| GET | `admin/dashboard/stats` | `dashboard.stats` | **JSON** — period filter, see [admin.md](admin.md#dashboard) |
| POST | `admin/notifications/{notification}/read` | `admin.notifications.read` | |
| POST | `admin/notifications/read-all` | `admin.notifications.read-all` | |
| resource | `admin/cats/adoption` (except `show`) | `admin.cats.adoption.*` | `Admin\Cats\AdoptionCatController` |
| DELETE | `admin/cats/adoption/{cat}/photos/{media}` | `admin.cats.adoption.photos.destroy` | |
| resource | `admin/cats/breeders` (except `show`) | `admin.cats.breeders.*` | `Admin\Cats\BreederCatController` |
| DELETE | `admin/cats/breeders/{cat}/photos/{media}` | `admin.cats.breeders.photos.destroy` | |
| resource | `admin/owners` (except `show`) | `admin.owners.*` | |
| resource | `admin/litters` (except `show`) | `admin.litters.*` | |
| resource | `admin/galleries` (except `show`) | `admin.galleries.*` | |
| resource | `admin/pages` (except `show`) | `admin.pages.*` | |
| POST | `admin/media/upload` | `admin.media.upload` | rich-text editor image upload, see [admin.md](admin.md#rich-text-editor) |
| resource | `admin/faq-items` (except `show`) | `admin.faq-items.*` | |
| resource | `admin/testimonials` (except `show`) | `admin.testimonials.*` | |
| resource | `admin/contact-requests` (`index`, `update`, `destroy` only) | `admin.contact-requests.*` | |
| GET | `admin/newsletter-subscribers` | `admin.newsletter-subscribers.index` | |
| GET | `admin/newsletter-subscribers/export` | `admin.newsletter-subscribers.export` | CSV, `;`-separated + UTF-8 BOM (French Excel) |
| PATCH | `admin/newsletter-subscribers/{id}/toggle-unsubscribed` | `admin.newsletter-subscribers.toggle-unsubscribed` | |
| GET | `admin/deposits` | `admin.deposits.index` | |
| GET | `admin/deposits/create` | `admin.deposits.create` | |
| POST | `admin/deposits` | `admin.deposits.store` | manual reservation — see [payments.md](payments.md#manual-admin-recorded-reservations--a-separate-simpler-path) |
| POST | `admin/deposits/{deposit}/mark-paid` | `admin.deposits.mark-paid` | |
| POST | `admin/deposits/{deposit}/verify-stripe` | `admin.deposits.verify-stripe` | `password.confirm` |
| POST | `admin/deposits/{deposit}/finalize` | `admin.deposits.finalize` | `password.confirm` |
| POST | `admin/deposits/{deposit}/assign-cat` | `admin.deposits.assign-cat` | turns a waiting-list deposit into a reservation for a specific cat |

## Admin — `role:super_admin` only

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `admin/settings` | `admin.settings.edit` | |
| PUT | `admin/settings` | `admin.settings.update` | |
| GET | `admin/activity-log` | `admin.activity-log.index` | read-only, no `password.confirm` |
| resource | `admin/users` (except `show`) | `admin.users.*` | `store`/`update`/`destroy` require `password.confirm` |
| POST | `admin/users/{user}/resend-reset-link` | `admin.users.resend-reset-link` | `password.confirm` |
| PATCH | `admin/users/{user}/toggle-active` | `admin.users.toggle-active` | `password.confirm` — blocked on the last active super_admin |
| POST | `admin/deposits/{deposit}/refund` | `admin.deposits.refund` | `password.confirm` |
| POST | `admin/deposits/{deposit}/cancel` | `admin.deposits.cancel` | `password.confirm` — undoes a paid deposit, releases the cat |
| POST | `admin/cats/finalize-directly` | `admin.cats.finalize-directly` | `password.confirm` — adoption with no `Deposit`-driven flow at all |

See [architecture.md](architecture.md#roles--permissions-authorization-layers)
for why these three tiers exist and how `password.confirm` fits in.
