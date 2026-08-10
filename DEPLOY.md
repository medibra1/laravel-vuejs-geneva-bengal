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

## 2. Process persistants (SSR, queue worker)

**Constat de l'audit** : rien n'était prévu pour la prod. `php artisan
inertia:start-ssr` (qui lance `node bootstrap/ssr/ssr.js`) n'est utilisé
qu'en dev via `docker-compose.yml` (service `ssr`, redémarré par Docker si
besoin) ; en prod classique PHP/Apache, rien ne garderait ce process actif
ni ne le relancerait après un crash ou un redémarrage serveur. Même
problème pour la queue : **les 7 classes `app/Notifications/*` et le job
`ReconcilePendingDeposits` implémentent toutes `ShouldQueue`** — sans un
worker qui tourne, aucune notification (confirmation d'acompte, contact,
newsletter...) n'est jamais réellement envoyée, les jobs restent en base
dans la table `jobs` indéfiniment.

La bonne réponse dépend du palier d'hébergement, très différent d'un
Infomaniak à l'autre :

### Sur Hébergement Web mutualisé (le plus probable ici, pas d'accès root)

Confirmé par la documentation Infomaniak (aucun Supervisor/systemd
disponible sur ce palier) :

- **SSR** : Infomaniak propose un vrai support Node.js sur l'hébergement
  mutualisé — un "site Node.js" configurable depuis le Manager
  (méthode de déploiement "Custom" : point d'entrée, port, commande de
  build définis à la main), avec un dashboard démarrer/arrêter/redémarrer
  qui gère lui-même la persistance du process (pas du Supervisor, mais le
  même besoin couvert par l'outil du panel). Pointer ce site Node.js sur
  `bootstrap/ssr/ssr.js`, port = celui mis dans `INERTIA_SSR_URL`. Si ce
  n'est pas configuré, mettre `INERTIA_SSR_ENABLED=false` : la passerelle
  Inertia retombe alors sur du rendu client seul (voir le commentaire déjà
  présent dans `docker-compose.yml` sur ce comportement de fallback) — le
  site reste fonctionnel, juste sans le bénéfice SEO du SSR (voir Phase 8
  du projet, CLAUDE.md).
- **Queue** : pas de process daemon possible du tout sur ce palier.
  Recommandé : `QUEUE_CONNECTION=sync` en prod — chaque notification part
  immédiatement, dans la requête HTTP qui la déclenche (formulaire de
  contact, webhook Stripe...), sans worker à maintenir. Coût réel : la
  requête attend l'envoi SMTP avant de répondre — acceptable pour le
  volume d'un site vitrine d'élevage, pas un problème de débit. Alternative
  si `QUEUE_CONNECTION=database` est gardé : déclencher
  `php artisan queue:work --stop-when-empty --max-time=50` via la même
  tâche planifiée que le scheduler (§4) plutôt qu'un vrai daemon — un
  "worker" qui se relance toutes les 15 minutes et vide la file du moment,
  pas un vrai temps réel mais fonctionnel sur ce palier.

### Sur Cloud Server (VPS, accès root/SSH)

Là, Supervisor s'installe normalement. Config fournie :
[`deploy/supervisor/geneva-bengal.conf`](deploy/supervisor/geneva-bengal.conf)
— deux programmes, `geneva-bengal-ssr` (`node bootstrap/ssr/ssr.js`
directement, pas le wrapper `artisan inertia:start-ssr`) et
`geneva-bengal-queue` (`php artisan queue:work`, `--tries=3` plutôt que le
`--tries=1` du `docker-compose.yml` de dev — un blip SMTP/Stripe
transitoire ne doit pas faire perdre une notification en prod). Ajuster
les chemins/utilisateur système en tête de fichier avant `supervisorctl
reread && supervisorctl update`. Dans ce cas `QUEUE_CONNECTION=database`
peut être gardé tel quel (pas besoin de `sync`).

## 3. Certificat SSL (validation ACME)

**Constat de l'audit** : pas de `.htaccess` à la racine du repo (normal —
Laravel n'en a jamais eu besoin, le document root pointe directement sur
`public/`, comme le confirme le guide officiel d'installation Laravel
d'Infomaniak : "pointer le dossier cible vers le sous-dossier `public`").
Le seul `.htaccess` pertinent est `public/.htaccess`, celui généré par
défaut par Laravel — ses règles de réécriture ont déjà chacune une
condition `RewriteCond %{REQUEST_FILENAME} !-f` : un fichier qui existe
réellement sur disque (donc un vrai fichier de challenge ACME déposé par
Infomaniak sous `public/.well-known/acme-challenge/`) n'était donc déjà
**pas** intercepté en pratique.

Corrigé quand même par une règle explicite (`^\.well-known/acme-challenge/
- [L]`, tout en haut, avant les autres) plutôt que de compter sur cette
condition implicite : plus robuste si une règle future (redirection HTTPS
forcée, etc.) est ajoutée dans ce fichier sans y repenser, et lisible
immédiatement pour qui relit ce `.htaccess` sans avoir à dérouler la
logique `!-f` de chaque règle en dessous.
