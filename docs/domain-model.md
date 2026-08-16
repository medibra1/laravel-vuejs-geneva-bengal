# Domain model

Single-tenant: one cattery, one dataset, no multi-tenancy. Public visitors
never have accounts — the only `users` rows are back-office admins. See
[architecture.md](architecture.md) for how these models are exposed
through controllers, and [payments.md](payments.md) for the `Deposit`
lifecycle in detail.

## Entity overview

```
Color ──┬──────────────< Cat >──────────< Deposit >──── Owner
        │  (color_id,        │  (cat_id,      (owner_id)
        │  second_color_id)  │   nullable)
        │                    │
        │                    ├──< Litter (sireLitters / damLitters,
        │                    │            by sire_cat_id / dam_cat_id)
        │                    └── Litter (litter_id — the litter this
        │                                cat was born into, if a kitten)
Gallery (standalone — homepage hero slides + photo gallery, see below)
Page ──< FaqItem (unrelated tables, both CMS content)
Testimonial (standalone CMS content)
SiteSetting (key/value store, see below)
ContactRequest (cat_id nullable fk, standalone otherwise)
NewsletterSubscriber (standalone)
User (admin/super_admin accounts only — see architecture.md#roles--permissions)
```

## `Cat` — the central model

One model, `type` enum (`CatType::Kitten|Cat|Breeder`) distinguishes what
the admin sees it as, not two tables — too many shared fields (photos,
color, sex, birth date) to justify splitting. The **admin UI** is split
into two controllers over this one table instead:

- `Admin\Cats\AdoptionCatController` — `type` in `[chaton, chat]`, shows
  status/price/availability, form request rejects `type = reproducteur`.
- `Admin\Cats\BreederCatController` — `type` forced to `reproducteur`
  server-side (never accepted from the form), shows sex/linked litters,
  no price/status/availability fields.

Both guard cross-section access explicitly (`ensureAdoptionType()` /
`ensureBreederType()`, `abort_unless(..., 404)`) since a shared table means
route-model-binding alone can't isolate them. The **public site** only
ever queries `type = CatType::Kitten` (`Public\CatController`) — breeders
have their own public page (`/nos-chats-reproducteurs`).

Key attributes:
- `status` — not a plain column. `spatie/laravel-model-status`
  (`HasStatuses`) keeps a full timestamped history in a separate
  `statuses` table (`CatStatus::Available|Pending|Adopted`), so "when did
  this cat become adopted" is queryable, not just "is it adopted now" —
  this is what powers the dashboard's adoptions-by-month chart (see
  [admin.md](admin.md#dashboard)).
- `description` — translatable (`spatie/laravel-translatable`,
  `HasTranslations`), JSON column under the hood, `{fr: ..., en: ...}`.
- `slug` — auto-generated from `name` (`spatie/laravel-sluggable`).
- Photos — `spatie/laravel-medialibrary`, `photos` collection, three
  non-queued webp conversions (`sm`/`md`/`lg`, see the doc comment on
  `Cat::registerMediaConversions()` for why non-queued: uploads only drain
  through the periodic `/cron/run` cycle, so a conversion must be ready
  immediately, not wait for that).
- Activity-logged (`LogsActivity`, `logOnlyDirty()`) — everything except
  `description` (translatable, potentially long free text) — see
  [admin.md](admin.md#activity-log).

## `Deposit` — reservation/payment record

Not created at checkout time — see [payments.md](payments.md) for the
full lifecycle (`PaymentIntentTracking`, webhook-as-sole-creator,
`DepositPaymentProcessor`). Once it exists:

- `cat_id` nullable — a deposit can target a specific cat, or be a
  generic waiting-list registration.
- `amount` — always an integer, in centimes/cents, never a float.
- `status` (`DepositStatus`) — `Pending|Paid|Failed|Refunded|Cancelled|Unavailable`.
  `Deposit::blocksNewReservation(int $catId)` only checks for an existing
  `Paid` deposit — a `Pending` one never blocks another visitor (see
  payments.md for why).
- `payment_method` (`PaymentMethod`, nullable) — `Stripe` for the public
  flow; an admin-recorded reservation can pick `Cash|BankTransfer|
  TwintManual`, or leave it `null` ("to be defined later") and resolve it
  when marking paid.
- `owner_id` (nullable) — resolved either at admin creation time or at
  `finalize()`.
- `locale` — captured once at creation (public flow only), so the
  confirmation email can be sent in the visitor's language even though
  it's actually dispatched later from a webhook/cron context with no
  request-local locale of its own.

## `Owner`, `Litter`, `Gallery`

- `Owner` — `first_name`/`last_name`/`email`/`phone`/`city` +
  `adoption_preference`. Admin CRUD only, linked from a finalized
  `Deposit` (adoption) or created inline during that flow.
- `Litter` — `sire_cat_id`/`dam_cat_id` (both nullable fks to `cats`,
  `nullOnDelete`), `expected_date`, `notes`. Public page
  `/portees-prevues` lists upcoming litters. `notes` is deliberately
  **not** translatable — free-text admin content, not the vue-i18n
  interface layer (see [i18n.md](i18n.md) for why mixing those two layers
  is a hard rule, not a convenience).
- `Gallery` — `type` enum (`GalleryType`) distinguishes homepage hero
  slides from the public photo gallery; `caption`, `position` for manual
  ordering, one `spatie/laravel-medialibrary` image per row.

## CMS content — `Page`, `FaqItem`, `Testimonial`, `SiteSetting`

This layer didn't exist in the original static template — it's new,
added so the client can edit copy without touching code. See
[i18n.md](i18n.md) for the distinction between this (layer 2: translated
content) and the vue-i18n interface strings (layer 3).

- **`Page`** — `slug` (sluggable), `menu_group` (nullable — only pages
  with one show up in the public dropdown nav, see
  `HandleInertiaRequests::share()`'s `menuPages` prop), `order`,
  `title`/`body`/`meta_title`/`meta_description` (all translatable),
  `is_published`. `body` is edited via a TipTap rich-text editor (see
  [admin.md](admin.md#rich-text-editor)) and sanitized server-side with
  `mews/purifier` before storage.
- **`FaqItem`** — `question`/`answer` (translatable), `order`.
- **`Testimonial`** — `author_name`, `quote` (translatable), `rating`
  (nullable), `is_published`, `order`.
- **`SiteSetting`** — key/value store (`value` JSON-cast), read through
  `SiteSetting::get($key, $default)`. Holds social links, address/phone/
  email, deposit amount, price range, default SEO text, logo (media
  collection on the row). ⚠️ `get()` must go through the real Eloquent
  model (`::first()?->value`, cast applied) — a raw
  `::value('value')` query-builder call bypasses the `array` cast and
  silently returns the raw JSON string instead (a real bug found and
  fixed on 2026-08-15, see `CLAUDE.md` for the full incident — it broke
  `deposit_amount` specifically, since that's the one setting consumed by
  a strictly-typed JS API, `stripe.elements()`).

Volontairily **not** admin-editable (avoids over-engineering a vitrine
site): page layout/structure, top-level public nav (Home/Kittens/About/
Contact/Gallery), the roles/permissions system itself.

## `ContactRequest`, `NewsletterSubscriber`

- **`ContactRequest`** — `reason` (`ContactReason`: adopt/waiting_list/
  question), `cat_id` nullable, `status` (`ContactStatus`), protected by
  `spatie/laravel-honeypot` on the public form.
- **`NewsletterSubscriber`** — `unsubscribe_token` (unique), `unsubscribed_at`
  (nullable). One-click unsubscribe (`/newsletter/unsubscribe/{token}`)
  is a compliance requirement, not just a feature — see CLAUDE.md 2026-08-07
  for the audit finding that flagged its absence. No third-party mailing
  list sync (Brevo integration was built once, then explicitly removed —
  export CSV is the only bulk-export path today).

## Seeding

`database/seeders/DatabaseSeeder.php` always runs, in order:
`RolesAndPermissionsSeeder` → `SuperAdminSeeder` → `ColorSeeder` →
`ContentPagesSeeder` → `SiteSettingsSeeder` → `HomeGallerySeeder`, then
`DemoDataSeeder` only outside production (`! app()->isProduction()`).

The first six are real content (roles, the bootstrap super_admin from
`SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` env vars, the fixed Bengal
color reference list, real CMS copy for the race-info/adoption/FAQ pages,
real site settings, and the homepage hero images) — without them a fresh
production deploy would go live with empty nav dropdowns, a blank
Settings form, and no hero images. `DemoDataSeeder` is Faker-generated
cats/owners/litters/galleries/testimonials/contact requests for local
development only.
