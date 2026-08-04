# Geneva Bengal

Site vitrine + back-office pour un élevage de chats Bengal basé à Genève, Suisse. Reconstruction complète de [genevabengals.ch](https://genevabengals.ch) avec une architecture propre, testée et livrée en tranches verticales (chaque module métier va du modèle de données jusqu'à l'interface, back et front ensemble).

Monorepo Laravel + Inertia — un seul déploiement, pas de SPA découplée.

## Stack

- **Backend** — Laravel 13 (PHP 8.3+), MySQL 8
- **Frontend** — Vue 3 (Composition API, `<script setup lang="ts">`), TypeScript, Inertia.js, PrimeVue 4 (thème Aura) + Tailwind CSS 4
- **i18n** — routing `/fr` `/en` ([mcamara/laravel-localization](https://github.com/mcamara/laravel-localization)), contenu traduisible ([spatie/laravel-translatable](https://github.com/spatie/laravel-translatable)), interface via [vue-i18n](https://vue-i18n.intlify.dev/)
- **Rôles** — `admin` / `super_admin` via [spatie/laravel-permission](https://github.com/spatie/laravel-permission), audit trail via [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)
- **Médias** — [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary)
- **Anti-spam** — [spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot) sur les formulaires publics (contact, infolettre, acompte)
- **Paiement** — Stripe Checkout (carte + TWINT) derrière une interface `PaymentGateway`, webhook + job de réconciliation quotidien
- **SEO** — [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap), hreflang fr/en, rendu côté serveur (SSR) pour Inertia
- **Tests** — [Pest](https://pestphp.com/) (backend), [Vitest](https://vitest.dev/) + Vue Test Utils (frontend)
- **Qualité** — [Laravel Pint](https://laravel.com/docs/pint), [Larastan](https://github.com/larastan/larastan)
- **CI** — GitHub Actions, deux workflows indépendants et path-filtrés (`backend.yml`, `frontend.yml`)

## Démarrer avec Docker (recommandé)

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

L'application est servie sur **http://localhost:8280**. Autres services :

| Service | URL |
|---|---|
| Adminer (DB) | http://localhost:8281 |
| Mailhog (emails de dev) | http://localhost:8025 |
| Vite dev server | http://localhost:5273 |
| Serveur SSR Inertia | http://localhost:13714 |

(Ports volontairement hors des plages 8080/8081/5173 par défaut, pour ne pas entrer en conflit avec d'autres projets Docker qui tournent en local.)

## Démarrer sans Docker

Prérequis : PHP 8.3+, Composer, Node 22, MySQL 8 (ou SQLite pour un dev rapide).

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed
npm run build   # ou `npm run dev` pour le hot-reload — inclut le bundle SSR (vite build --ssr)
php artisan serve
php artisan inertia:start-ssr   # optionnel : active le rendu SSR (sinon fallback client-only automatique)
```

Le seeder de rôles/super-admin lit `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` depuis `.env` — à définir avant de seed.

Pour tester le paiement en local, `stripe listen --forward-to localhost:8280/webhooks/stripe` (Stripe CLI) donne un `STRIPE_WEBHOOK_SECRET` à mettre dans `.env`, en plus des clés `STRIPE_KEY`/`STRIPE_SECRET` en mode test.

## Tests

```bash
./vendor/bin/pest              # backend
./vendor/bin/pint --test       # style de code
./vendor/bin/phpstan analyse   # analyse statique (Larastan)

npm run test                   # frontend (Vitest)
npm run build                  # build + vérification TypeScript (vue-tsc)
```

Les deux workflows CI (`backend.yml`, `frontend.yml`) sont volontairement découplés : le premier ne dépend jamais de `node_modules`/build compilé, le second jamais de `vendor/`.

## État du projet

- [x] Fondations — Laravel, Inertia/Vue/TypeScript, PrimeVue, Tailwind, i18n, Docker, CI, rôles
- [x] Module Chats — fiche chat (traductions, statut historisé, photos), CRUD admin, liste/fiche publiques
- [x] Adoptants, portées, galeries — CRUD admin
- [x] CMS (pages de contenu, FAQ, témoignages, réglages du site) — CRUD admin, menu public généré dynamiquement
- [x] Formulaire de contact public + inscription infolettre (anti-spam honeypot)
- [x] Gestion des comptes admin (`super_admin`) — création via lien de réinitialisation, désactivation, audit trail (`activitylog`)
- [x] Page d'accueil publique
- [x] Refonte admin — sidebar avec logo, navigation groupée
- [x] Paiement d'acompte (Stripe Checkout, carte + TWINT — webhook, remboursement, réconciliation)
- [x] Tableau de bord admin (KPIs, graphiques filtrés par période)
- [x] SEO — sitemap, hreflang, rendu côté serveur (SSR)

Toutes les phases prévues sont livrées. ⚠️ Le paiement est développé et
testé en mode test Stripe uniquement — un compte Stripe réel et vérifié
par le client reste nécessaire avant la mise en production.

## Licence

Propriétaire — projet client.
