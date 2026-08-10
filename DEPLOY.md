# Déploiement en production (hébergement Infomaniak)

Ce document couvre le passage de ce repo (Laravel 13 + Inertia/Vue SSR,
dockerisé en dev uniquement) vers un hébergement Infomaniak classique
PHP/Apache — sans Docker côté serveur. Écrit pour l'offre **Hébergement
Web (mutualisé)**, avec les différences notées quand l'offre **Cloud
Server** (VPS avec accès root) change la réponse.

Le `docker-compose.yml` et les workflows `.github/workflows/*.yml` restent
inchangés par ce document : ils décrivent l'environnement de dev/CI, pas la
prod.

## 1. Variables d'environnement (`.env` en prod)

`.env.example` documente déjà toutes les clés ; voici la checklist de ce
qui **doit** être redéfini en prod (aucune valeur sensible listée ici,
juste les noms de clé et ce qu'elles doivent contenir) :

| Variable | À faire en prod |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — jamais `true`, fuite de stack traces/valeurs d'env aux visiteurs |
| `APP_KEY` | générée sur le serveur (`php artisan key:generate --force`), jamais copiée d'un autre environnement |
| `APP_URL` | domaine réel, schéma `https://` inclus — pilote toute URL générée (assets, media, sitemap, mails) |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | base MySQL fournie par Infomaniak (pas `sqlite`, valeur de dev) |
| `SESSION_DOMAIN` | host nu du domaine (ex. `genevabengals.ch`, sans schéma) — voir §5 |
| `SESSION_SECURE_COOKIE` | `true` (site servi en HTTPS) |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | SMTP réel (Infomaniak Mail Hosting ou autre) — `MAIL_MAILER=log` (défaut dev) n'envoie jamais rien |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | clés **live** du compte Stripe réel du client, pas les clés de test — voir CLAUDE.md, un vrai compte Stripe vérifié est un prérequis produit distinct de ce déploiement technique |
| `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` | lues une seule fois par `SuperAdminSeeder` au premier `migrate --seed` — changer le mot de passe ensuite depuis l'admin, ne pas garder cette valeur en tête |
| `INERTIA_SSR_ENABLED` / `INERTIA_SSR_URL` | voir §2 — dépend de comment le process SSR est exposé en prod |
| `CRON_SECRET` | voir §4 — protège l'endpoint qui remplace le cron classique sur l'hébergement mutualisé |
| `QUEUE_CONNECTION` | voir §2 — `sync` recommandé sur mutualisé, `database` si un vrai worker tourne (Cloud Server) |

Point vérifié et **volontairement absent** de cette liste : **Sanctum**
(`laravel/sanctum` est une dépendance Composer héritée de Breeze, mais
n'est ni configuré — pas de `config/sanctum.php` publié, pas de
`config/cors.php` — ni utilisé nulle part dans `app/`/`bootstrap/app.php`,
voir grep fait pendant cet audit). L'app n'a que de l'auth par session
classique, aucune API stateful consommée en cross-domain. `SANCTUM_STATEFUL_DOMAINS`
n'a donc pas lieu d'être définie ; si un jour une vraie API SPA-token
apparaît, il faudra d'abord publier/câbler Sanctum avant que cette
variable ait un effet.
