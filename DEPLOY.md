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


## 4. Tâche planifiée (job de réconciliation Stripe)

`routes/console.php` : `Schedule::job(new ReconcilePendingDeposits)->daily();`
— syntaxe Laravel 11+ (pas de `app/Console/Kernel.php` dans ce repo, ce
projet est en Laravel 13). Le déclenchement lui-même est déjà correct,
rien à changer côté code — mais **rien n'exécute jamais ce planificateur**
sans une tâche externe qui appelle `schedule:run` régulièrement, et
comment faire ça diffère radicalement entre les deux paliers Infomaniak :

### Sur Cloud Server (VPS, crontab réel)

Le classique :
```
* * * * * cd /var/www/geneva-bengal && php artisan schedule:run >> /dev/null 2>&1
```

### Sur Hébergement Web mutualisé — pas de vrai crontab

Vérifié précisément (documentation Infomaniak) : le "Planificateur de
tâches" de ce palier n'exécute **pas** une commande shell, il appelle une
**URL en HTTP** à intervalle choisi, avec un minimum de **15 minutes**
(contre 1 minute sur Cloud Server). Impossible donc d'y coller directement
`php artisan schedule:run` en ligne de commande.

Solution retenue : une route dédiée qui déclenche `schedule:run` **et**
`queue:work --stop-when-empty --max-time=50` en interne (le mutualisé ne
permet pas non plus de laisser un worker de queue tourner en démon, voir
§2 — cette route sert donc aussi de traitement de queue périodique),
protégée par un jeton (sinon n'importe qui trouvant l'URL pourrait forcer
l'exécution des tâches planifiées ou vider la queue à volonté) —
[`routes/web.php`](routes/web.php), `GET /cron/run?token=...`,
comparaison en `hash_equals()` contre `config('app.cron_secret')` (lu
depuis `CRON_SECRET` en `.env`, voir §1). À configurer dans le
Planificateur de tâches Infomaniak :
```
https://<domaine>/cron/run?token=<CRON_SECRET>
```
Intervalle 15 minutes, aligné sur la grille de l'heure (`:00`, `:15`,
`:30`, `:45` — pas un décalage arbitraire type `:05`/`:20`) : `daily()`
s'exécute par défaut à minuit pile, qui tombe justement sur cette grille —
un décalage la manquerait le jour même (elle ne rattrape pas, la
prochaine fenêtre due est le lendemain).

Si l'offre Infomaniak permet en plus de protéger l'URL par mot de passe
au niveau du Planificateur lui-même (vu dans leur documentation), l'activer
en plus du jeton applicatif — défense en profondeur, pas une redondance
inutile : le mot de passe Infomaniak protège contre la découverte de
l'URL, le jeton applicatif reste valable même si ce mot de passe fuit
autrement.

## 5. Domaine : de `dev.genevabengals.ch` au domaine final

**Vérifié par grep sur tout `app/`, `config/`, `routes/`,
`resources/js/`** : aucun domaine n'est codé en dur dans le code applicatif
(le seul résultat est un commentaire dans `resources/js/Pages/Public/Page.vue`
qui *mentionne* `genevabengals.ch` à titre d'exemple, pas une valeur
utilisée). Tout ce qui génère une URL — assets Vite, medialibrary
(`config/filesystems.php`, disque `public`), sitemap (`SitemapController`
utilise la façade `URL`), liens de partage sociaux — dérive de `APP_URL`
au runtime, pas d'une constante. Seule exception, hors périmètre de ce
document : `docker-compose.yml` a `MAIL_FROM_ADDRESS: no-reply@genevabengals.ch`
en dur, mais c'est un fichier de dev, explicitement non touché ici.

Donc : passer de `dev.genevabengals.ch` au domaine final ne demande **que**
des changements de `.env`, aucun changement de code :

- `APP_URL` → `https://<domaine final>`
- `SESSION_DOMAIN` → host nu du domaine final (ex. `genevabengals.ch`, sans
  `https://` ni chemin) — doit correspondre exactement au domaine servi,
  sinon le navigateur refuse silencieusement le cookie de session (symptôme
  classique : login admin qui semble réussir puis relogue aussitôt).
- `SANCTUM_STATEFUL_DOMAINS` : **non applicable**, voir §1 — Sanctum n'est
  pas câblé dans cette app, rien à définir ici tant que ça reste vrai.

Après changement, invalider les caches de config sur le serveur
(`php artisan config:clear` puis `config:cache`, voir §6) — `APP_URL`/
`SESSION_DOMAIN` sont lus depuis un cache compilé une fois
`config:cache` exécuté, un `.env` modifié seul ne suffit pas à les faire
prendre effet immédiatement.

## 6. Script de déploiement

[`deploy.sh`](deploy.sh) — à exécuter **sur le serveur**, en SSH, depuis
la racine du projet (l'accès SSH est disponible sur l'offre Hébergement
Web mutualisé d'Infomaniak, créé depuis la section FTP/SSH du Manager ;
c'est ce qui permet de lancer `composer`/`npm`/`artisan` du tout). Il n'y
a pas d'étape de déploiement continu dans `.github/workflows/*.yml` —
ceux-ci ne font que tester/builder, pas déployer — donc ce script reste
le mécanisme de déploiement réel, lancé à la main pour l'instant :

```
ssh <user>@<host>
cd /chemin/vers/le/projet
./deploy.sh
```

Étapes, dans l'ordre : `git pull --ff-only`, `composer install --no-dev
--optimize-autoloader`, `npm ci && npm run build` (le script `build` de
`package.json` fait déjà `vue-tsc && vite build && vite build --ssr` — un
seul appel construit le bundle client **et** le bundle SSR), mode
maintenance (`artisan down`/`up` autour de la migration, pour ne jamais
servir de requête au milieu d'un schéma à moitié migré), `migrate
--force`, `storage:link` (idempotent), cache config/routes/vues, puis
redémarrage du process SSR — `supervisorctl restart` si disponible (Cloud
Server), sinon message rappelant de redémarrer le site Node.js depuis le
Manager Infomaniak (mutualisé, voir §2).

Prérequis explicitement **hors du script** (jamais générés/écrits par
lui) : `.env` déjà présent sur le serveur avec les vraies valeurs de prod
(§1) — le script s'arrête tout de suite s'il ne le trouve pas, plutôt que
de continuer avec un `.env.example` copié par erreur.

Non couvert par ce script, à faire une seule fois lors du tout premier
déploiement (pas à chaque déploiement suivant) : création de la base
MySQL et de son utilisateur dans le Manager Infomaniak,
`php artisan key:generate --force`, configuration du site Node.js pour le
SSR (§2) et du Planificateur de tâches pour `/cron/run` (§4).
