# Database schema

Effective schema (final state after all migrations under
`database/migrations/`, not a chronological diff — check that directory
if you need to know *when* a column was added). All tables use InnoDB,
`id` bigint auto-increment primary keys, and standard `created_at`/
`updated_at` timestamps unless noted otherwise. Amounts are always
integers (cents/centimes), never floats. See
[domain-model.md](domain-model.md) for the model-level relationships and
business rules, [payments.md](payments.md) for the deposit/payment tables
specifically.

## Business & content tables

### `cats`
| Column | Type | Notes |
|---|---|---|
| `slug` | string, unique | auto-generated from `name` (`spatie/laravel-sluggable`) |
| `name` | string | |
| `type` | string | `CatType` enum: `chaton` \| `chat` \| `reproducteur` |
| `sex` | string | `CatSex` enum |
| `color_id` | fk → `colors`, required | |
| `second_color_id` | fk → `colors`, nullable | bicolor cats |
| `description` | json, nullable | translatable (`{fr, en}`) |
| `price` | unsigned int, nullable | cents |
| `birth_date` | date, nullable | |
| `eye_color` | string, nullable | |
| `available_at` | date, nullable | |
| `diet` | string, nullable | |
| `litter_trained` | boolean, default `false` | |
| `neutered` | boolean, default `false` | |
| `litter_id` | fk → `litters`, nullable, `nullOnDelete` | the litter this cat was *born into*, distinct from `sireLitters`/`damLitters` below |

Status is **not** a column — see the `statuses` table below
(`spatie/laravel-model-status`).

### `colors`
`name`, `hex_code`, `slug` (nullable, unique — added later, backfilled via
`Str::slug($name)`; used by the public color-filter route,
`cats.index.color`).

### `owners`
`first_name`, `last_name`, `email`, `phone` (nullable), `city` (nullable),
`desired_cat_id` (fk → `cats`, nullable, `nullOnDelete`), `desired_color_id`
(fk → `colors`, nullable, `nullOnDelete`) — the last two are independent of
each other (a waiting-list owner may want a specific cat, a specific
color, both, or neither yet).

### `litters`
`sire_cat_id` (fk → `cats`, nullable, `nullOnDelete`), `dam_cat_id` (fk →
`cats`, nullable, `nullOnDelete`), `expected_date` (date, nullable),
`notes` (text, nullable — deliberately not translatable, see
[i18n.md](i18n.md)).

### `galleries`
`caption` (string, nullable), `position` (unsigned int, default `0`),
`type` (string, default `gallery` — `GalleryType` enum: distinguishes
homepage hero slides / social tiles from the public photo gallery). Unique
composite index `(type, position)` — enforced at the DB level so two rows
of the same type can never collide on ordering, not just an app-level
convention.

### `pages`
`slug` (unique), `menu_group` (nullable — only pages with one appear in
the public dropdown nav), `order` (unsigned int, default `0`), `title`
(json, required, translatable), `body` (json, nullable, translatable —
rich HTML via TipTap, sanitized server-side, see
[admin.md](admin.md#rich-text-editor)), `meta_title`/`meta_description`
(json, nullable, translatable), `is_published` (boolean, default `false`).

### `faq_items`
`slug` (unique, derived from the French question text — the real dedup
constraint for `ContentPagesSeeder`), `question`/`answer` (json, required,
translatable), `order` (unsigned int, default `0`).

### `testimonials`
`author_name`, `quote` (json, translatable), `rating` (unsigned tinyint,
nullable), `is_published` (boolean, default `false`), `order` (unsigned
int, default `0`).

### `site_settings`
`key` (string, unique), `value` (json, nullable — always read/written
through the `SiteSetting` model's cast, never a raw query builder call,
see [domain-model.md](domain-model.md#cms-content--page-faqitem-testimonial-sitesetting)
for a real bug this caused once).

### `contact_requests`
`name`, `email`, `reason` (string — `ContactReason` enum), `cat_id` (fk →
`cats`, nullable, `nullOnDelete`), `city` (nullable), `message` (text),
`status` (string, default `new` — `ContactStatus` enum).

### `newsletter_subscribers`
`email` (unique), `unsubscribe_token` (string(64), nullable, unique —
backfilled for pre-existing rows when this column was added),
`unsubscribed_at` (timestamp, nullable).

## Payment tables

See [payments.md](payments.md) for the full lifecycle these tables
support.

### `deposits`
| Column | Type | Notes |
|---|---|---|
| `cat_id` | fk → `cats`, nullable, `nullOnDelete` | `null` = generic waiting-list registration |
| `owner_id` | fk → `owners`, nullable, `nullOnDelete` | resolved at admin creation or at `finalize()` |
| `name`, `email`, `phone` | string (`phone` nullable) | |
| `amount` | unsigned int | cents, never a float |
| `currency` | string(3), default `CHF` | |
| `status` | string, default `pending` | `DepositStatus` enum: `pending`\|`paid`\|`failed`\|`refunded`\|`cancelled`\|`unavailable` |
| `provider` | string, nullable | `stripe` for the public flow; mirrors `payment_method` for admin-recorded ones (never defaults to `stripe` for a cash/bank/TWINT-manual deposit) |
| `locale` | string(5), nullable | captured once at public checkout; `null` for admin-created deposits — see [i18n.md](i18n.md) |
| `provider_reference` | string, nullable, indexed | Stripe PaymentIntent id |
| `payment_link_url` | text, nullable | legacy field from the admin Stripe-link flow, since removed as an option (see payments.md) |
| `payment_method` | string, nullable | `PaymentMethod` enum; `null` = "to be defined later" (admin flow only) |
| `created_by` | fk → `users`, nullable, `nullOnDelete` | `null` = created through the public flow |
| `paid_at` | timestamp, nullable | |
| `finalized_at` | timestamp, nullable | set by `finalize()` — adoption completed, owner linked |
| `confirmation_sent_at` | timestamp, nullable | set only once the client confirmation email actually sent — see `ReconcileCheckouts` |
| `confirmation_attempts` | unsigned int, default `0` | capped retry counter for the above |

`payment_method` and `provider` are both nullable specifically to support
"payment method to be defined later" on an admin-recorded reservation —
see the migration comment for why the column default (`stripe`) was kept
even after making the column nullable (it only ever applies when the
column is omitted from an `INSERT`, still true for the public flow).

### `payment_intent_tracking`
Replaced `checkout_holds` (dropped, see below) on 2026-08-14. `id`,
`payment_intent_id` (string, unique), timestamps. **Not** a reservation
mechanism — no lock, no TTL, no relation to `cat_id`. Purely a breadcrumb
written right after Stripe confirms PaymentIntent creation (before the
`client_secret` is even returned to the browser) so `ReconcileCheckouts`
can find a PaymentIntent whose webhook never arrived. Deleted the moment
a real `Deposit` is built from it.

⚠️ `checkout_holds` (dropped in the same migration that created this
table) was an earlier design: `cat_id` (unique fk), `payment_intent_id`
(unique), a sliding `expires_at` + fixed `hard_expires_at`. Abandoned
because it blocked *every* visitor landing on a checkout page, not just
ones who committed to paying. Mentioned here only so its absence isn't
mistaken for an oversight if you're diffing against an older branch.

## Infrastructure tables (package-owned, not hand-designed)

| Table | Owner | Purpose |
|---|---|---|
| `users`, `password_reset_tokens`, `sessions` | Laravel/Breeze | `users` has two additions beyond stock Breeze: `is_active` (boolean, default `true`) and `last_login_at` (timestamp, nullable) |
| `cache`, `cache_locks` | Laravel | |
| `jobs`, `job_batches`, `failed_jobs` | Laravel queue | drained via `/cron/run`, see [architecture.md](architecture.md#the-cronrun-endpoint--why-it-exists) |
| `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` | `spatie/laravel-permission` | see [architecture.md](architecture.md#roles--permissions-authorization-layers) |
| `statuses` | `spatie/laravel-model-status` | polymorphic (`model_type`/`model_id`), timestamped — this is where `Cat` status history actually lives, not a column on `cats` |
| `media` | `spatie/laravel-medialibrary` | polymorphic, one row per uploaded file + conversions/responsive image metadata |
| `activity_log` | `spatie/laravel-activitylog` | polymorphic `subject`/`causer`, `log_name`, `event`, `properties` (json diff) — see [admin.md](admin.md#activity-log) |
| `notifications` | Laravel notifications (`database` channel) | uuid primary key, polymorphic `notifiable`, `data` (json) — see [admin.md](admin.md#notifications-in-app--email) |
| `editor_uploads` | app-owned, no content columns | pure medialibrary anchor for TipTap image uploads, see [admin.md](admin.md#rich-text-editor) |

## Foreign key delete behavior — the pattern

Every app-defined foreign key on `cats`/`litters`/`owners`/
`contact_requests`/`deposits` uses `nullOnDelete()`, never the database
default (`RESTRICT`) and never `cascadeOnDelete()`: deleting the parent
shouldn't be blocked, and the child record should still make sense with
the reference cleared — e.g. deleting a `Cat` shouldn't be blocked by an
old `ContactRequest` that once referenced it, deleting a `Litter`
shouldn't cascade into deleting the parent `Cat`s born from it, and
deleting a `User` shouldn't cascade into deleting the `Deposit`s they
recorded (`created_by` just goes `null`).
`cascadeOnDelete()` only appears in package-owned tables
(`spatie/laravel-permission`'s pivot tables) — no app table uses it.
