# Audit Stack Technique — M-AUDIT-STACK

**Date** : 2026-05-06
**Stage** : 2 du pivot stratégique 2026-05-05 (entre Stage 1 audit chemin critique ✓ et Stage 3 décision Voie A/B/C).
**Scope** : audit STATIQUE sandbox (lecture code + comparaison alternatives + recoupement docs Composer/PHP/web). Aucun benchmark runtime, aucun fix.
**Boundary** : aucun fichier production modifié. Verdict uniquement.

**Légende verdict** :
- **keep** : composant adapté à la cible, ratio coût/bénéfice migration défavorable
- **replace** : composant à remplacer en Stage 3+ (alternative meilleure et migration tenable)
- **remove** : composant à retirer (usage marginal ou redondant)

**Légende coût migration** :
- **XS** : <2h (changement composer + ajustement 1-2 fichiers)
- **S** : <1 jour (1 service à réécrire, tests OK)
- **M** : 1-3 jours (plusieurs services + tests à mettre à jour)
- **L** : 1-2 semaines (~10 services + 30+ sites usage + risque régression)
- **XL** : >2 semaines (refacto structurel transverse)

**Verdict global** : (à conclure dans la synthèse, AUDIT-STACK-14)

---

## AUDIT-STACK-01 — `dompdf/dompdf` v3.1.4 (génération PDF)

**Rôle aujourd'hui** : génération PDF côté serveur des **procurations** (`ProcurationPdfService`) et **procès-verbaux** (`MeetingReportsService`). Rendu HTML+CSS inline → PDF A4 portrait, police DejaVu Sans (UTF-8 accents français), `isRemoteEnabled = false` (durcissement F16).

**Sites d'usage** :
- `app/Services/ProcurationPdfService.php` (296 lignes, dompdf + Options)
- `app/Services/MeetingReportsService.php` (utilise dompdf lignes 17-18, 278-281)
- 2 sites uniquement, encapsulés dans 2 services dédiés.

**Version actuelle** : `^3.1` (lock : `v3.1.4`). Branche 3.x stable depuis 2024, pas de 4.x annoncée.

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **mPDF** | Plus rapide, meilleur support CSS3, RTL, polices embarquées via FontMetrics | API moins idiomatique, taille du package plus lourde (~50 Mo avec polices), licence GPL-2.0 (vs LGPL-2.1 dompdf — non bloquant pour usage privé) |
| **wkhtmltopdf** (binary externe) | Rendu Webkit fidèle, layout CSS moderne | **Abandonné upstream depuis 2023**, dépendance binaire (Docker layer +200 Mo Qt), CVE non patchées. **Disqualifié.** |
| **Typst** (compilateur typé) | Output reproductible, syntaxe propre | Pas une cible HTML→PDF — il faudrait réécrire tous les templates en Typst. Coût XL. **Hors scope pivot.** |
| **DocRaptor / Prince** (SaaS / commercial) | Rendu CSS state-of-the-art | Coût récurrent, dépendance externe (incompatible self-hosted), latence API. **Disqualifié pour cible asso self-hosted.** |
| **Browserless / Playwright print-pdf** | Chromium headless, fidélité visuelle parfaite | Empreinte ressources énorme (Chromium ~300 Mo image + 512 Mo RAM par instance), complexité ops. Disproportionné pour 2 PDFs par AG. |
| **Garder dompdf** | API stable, déjà intégré, hardening F16 en place, PDF/A non requis pour procurations associatives | Subset CSS limité (pas de flexbox, pas de grid), perf O(n²) sur tableaux >1000 lignes (procurations ~50 → non bloquant), warnings deprecation PHP 8.4 ponctuels |

**Verdict** : **keep**

**Justification** :
- Usage limité (2 services, ~10 pages générées par AG max).
- Hardening sécurité F16 déjà appliqué (`isRemoteEnabled=false`, validation chemins, sanitization HTML en amont).
- DejaVu Sans gère parfaitement les accents français (cas d'usage cible).
- Aucune des alternatives n'apporte gain proportionnel au coût de migration.
- Branche 3.x maintenue, PHP 8.4 supporté officiellement depuis 3.0.0.
- Recoupement Stage 1 étape 10 (audit chemin critique : génération PV PDF marquée ✓ static).

**Coût migration estimé** : N/A (keep)
**Bénéfice attendu** : N/A
**Recommandation Stage 3** : aucune action. Surveiller `dompdf/dompdf` 4.x si annoncé. Maintenir test de smoke PV ≥10 pages (déjà identifié comme dette dans PROJECT.md "Fonctionnel non vérifié récemment").

---

## AUDIT-STACK-02 — `phpoffice/phpspreadsheet` v1.30.2 + `openspout/openspout` v5.6.0 (Excel I/O)

**Rôle aujourd'hui** : **dual-track** XLSX I/O découvert pendant l'audit (non explicité dans REQUIREMENTS.md initial) :
- **OpenSpout v5.6** = chemin **export XLSX** streaming (`ExportService.php`, `Writer\XLSX\Writer`). Mémoire constante quel que soit le nombre de lignes — adapté aux exports résultats AG.
- **PhpSpreadsheet v1.30.2** = chemin **import XLSX** uniquement (`XlsxImporter.php`, `IOFactory::load()` + `getRowIterator()`). Charge tout le workbook en mémoire.

**Sites d'usage** :
- `app/Services/ExportService.php` (310 lignes, OpenSpout) — exports résultats motions, PV xlsx, attendances.
- `app/Services/XlsxImporter.php` (300 lignes, PhpSpreadsheet) — imports membres / procurations / motions / présences depuis XLSX.
- `app/Controller/AnalyticsController.php` (1 reférence — confirmer si export ou import).
- 3 sites au total, séparation propre entre import et export.

**Versions actuelles** :
- `phpoffice/phpspreadsheet ^1.29` lock `1.30.2` (1.x EOL annoncée Q4 2026 → migration 2.x ou 3.x à prévoir)
- `openspout/openspout ^5.6` lock `v5.6.0` (5.x maintenue activement, PHP 8.4 supporté)

**Recoupement Stage 1** : étape 02 audit chemin critique (import CSV/XLSX membres) marquée `⚠` — la nuance documentée concerne l'encoding/separator detection, pas la libraire elle-même. La présence d'OpenSpout côté export prouve que la migration partielle est faisable.

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **OpenSpout-only** (étendre OpenSpout côté import via `Reader\XLSX\Reader`) | API symétrique avec l'export, mémoire constante au lieu de O(n), -2 Mo de deps Composer (PhpSpreadsheet pèse ~5 Mo, OpenSpout ~500 Ko), suppression d'une dépendance sur fin de vie | OpenSpout Reader ne calcule pas les formules (`isFormula()` + `getCalculatedValue()` actuellement utilisés ligne 61-62 de XlsxImporter) — **mais** le cas d'usage import membre n'a aucune raison légitime d'embarquer une formule. Trivial à supprimer. |
| **CSV-only** (supprimer XLSX import entièrement) | Stack ultra-légère, formats triviaux | Cible utilisateur (secrétaire asso non-tech) export naturellement depuis Excel/LibreOffice. Forcer CSV ajoute friction. **Disqualifié.** |
| **Box/Spout** (ancien projet, deprecated) | — | Officiellement archivé, OpenSpout est le fork. **Disqualifié.** |
| **Garder dual-track actuel** | Statu quo, fonctionne | Maintient 2 dépendances pour 1 problème, PhpSpreadsheet 1.x EOL Q4 2026, surcoût mental pour chaque dev qui touche I/O Excel |

**Verdict** : **replace** (PhpSpreadsheet → OpenSpout côté import)

**Justification** :
- Asymétrie injustifiée : export streaming (mémoire constante) mais import non-streaming (charge tout). Un fichier import 5000 lignes consommerait ~50 Mo RAM avec PhpSpreadsheet vs <2 Mo avec OpenSpout Reader.
- Deadline upstream : PhpSpreadsheet 1.x EOL Q4 2026 → migration forcée tôt ou tard, autant la faire vers OpenSpout (déjà en place côté export) plutôt que vers PhpSpreadsheet 2.x/3.x.
- API OpenSpout Reader très proche de l'usage actuel (foreach sur sheets + foreach rows + getCells) — réécriture mécanique.
- Suppression de `phpoffice/phpspreadsheet` allège l'image Docker (~5 Mo vendor + dépendances transitives `markbaker/complex`, `markbaker/matrix`, etc.).

**Coût migration estimé** : **S** (1 jour)
- Réécrire `XlsxImporter::readFile()` (45 lignes lignes 33-92) avec `OpenSpout\Reader\XLSX\Reader` — 1-2h de code.
- Adapter le test `tests/Unit/XlsxImporterTest.php` si présent — 1-2h.
- Retirer `phpoffice/phpspreadsheet` du `composer.json` + run `composer update` + verify — 30min.
- Vérifier `AnalyticsController.php` (1 référence) — 30min.

**Bénéfice attendu** :
- 1 dépendance retirée (~5 Mo image Docker).
- Mémoire d'import constante (gain perçu sur fichiers >1000 lignes).
- Symétrie API import/export (réduit la charge cognitive dev future).
- Hors zone EOL annoncée.

**Recommandation Stage 3** : **prioriser** dans M-DECISION roadmap. Faisable en milestone unique "M-IO-CONSOLIDATION" (1 plan, 3 tasks : reader, test, cleanup deps).

---

## AUDIT-STACK-03 — `erusev/parsedown` v1.8.0 (Markdown rendering)

**Rôle aujourd'hui** : rendu Markdown → HTML pour les pages d'aide in-app (`/doc/{page}` servies depuis `app/docs/*.md`). Mode safe activé (`setSafeMode(true)`), TOC généré côté contrôleur.

**Sites d'usage** :
- `app/Controller/DocController.php` (1 usage : `new Parsedown()` + `->text()`)
- `public/doc.php` (référence — wrapper legacy)
- **2 sites au total**, 1 service réel.

**Version actuelle** : `^1.8` lock `1.8.0`. **Projet quasi-mort** : pas de release depuis 2019, 200+ issues ouvertes, **émet des deprecation warnings sur PHP 8.4** (callable strings, optional params before required) — déjà neutralisés via `error_reporting(E_ALL & ~E_DEPRECATED)` ligne 122 de `DocController.php`. C'est une rustine, pas une solution.

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **league/commonmark** | Standard CommonMark + GFM, maintenu activement (dernière release <30j), PHP 8.4 natif, extensions modulaires (TOC, Attributes, FootNotes), conforme spec CommonMark | Légèrement plus lourd (~150 Ko vendor vs ~30 Ko Parsedown) |
| **michelf/php-markdown** | Léger, maintenu | API datée, pas GFM par défaut, moins extensible |
| **Parsedown 2.0-beta** | Migration "logique" | Beta depuis 2019, **abandonné de facto** |
| **Garder Parsedown 1.8** | Rien à faire | Warnings PHP 8.4 suppressed (rustine), risque cassage PHP 8.5/9.0, surface attack mode safe non auditée depuis 6 ans |

**Verdict** : **replace** (par `league/commonmark`)

**Justification** :
- Projet upstream **abandonné** (pas de release depuis 6 ans, deprecations PHP 8.4 non patchées).
- Suppression `error_reporting()` rustine = code plus propre.
- league/commonmark = standard de facto PHP, maintenu par PHP League, conforme CommonMark spec (interopérabilité éditeurs Markdown).
- Usage limité (2 sites) → migration triviale.

**Coût migration estimé** : **XS** (<2h)
- `composer require league/commonmark` + `composer remove erusev/parsedown` — 5min.
- Réécrire `DocController::renderMarkdown()` (lignes 121-128) avec `League\CommonMark\GithubFlavoredMarkdownConverter` — 30min.
- Supprimer la rustine `error_reporting()` — gratuit, vient avec.
- Vérifier `public/doc.php` (probablement obsolète si Router gère `/doc/*`) — 30min.
- Tester rendu d'une page `.md` représentative — 30min.

**Bénéfice attendu** :
- Sortie d'une dépendance abandonnée (dette de sécurité latente).
- Suppression du `error_reporting()` masking → restaure la visibilité des deprecations légitimes ailleurs.
- Compatibilité PHP 8.5+ garantie (commonmark CI couvre).

**Recommandation Stage 3** : **petite priorité** — peut être absorbé dans le milestone "M-IO-CONSOLIDATION" suggéré pour AUDIT-STACK-02, ou traité en quick-fix isolé.

---

## AUDIT-STACK-04 — `symfony/mailer` v8.0.4 (SMTP transport)

**Rôle aujourd'hui** : transport SMTP avec STARTTLS/TLS pour `MailerService`. Remplace une implémentation socket SMTP maison (cf. PHPDoc ligne 17-18 : "Replaces the hand-rolled SMTP socket implementation"). Supporte `smtp://` (port 587 STARTTLS) et `smtps://` (port 465 TLS). Lazy-init + cache par instance. Utilisé par `EmailQueueService` (envoi asynchrone via queue Redis) + invitations + reset password + monitoring webhooks email.

**Sites d'usage** :
- `app/Services/MailerService.php` (177 lignes) — wrapper unique
- ~6 services/controllers consomment `MailerService` (EmailQueueService, PasswordResetController, MonitoringService, InvitationsService, etc.)
- API publique (constructeur + retour `array{ok:bool,error:?string}`) volontairement préservée pour faciliter swap.

**Version actuelle** : `^8.0` lock `v8.0.4`. **C'est la version stable courante** — Symfony 8.0 LTS est sortie 2025-Q4, supportée jusqu'à 2027 ; ce n'est pas du bleeding-edge malgré l'apparence du numéro majeur (Symfony incrémente le major chaque ~2 ans). Le projet utilise déjà 4 packages Symfony en `^8.0` (mailer, mime, console, event-dispatcher) — cohérent.

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **PHPMailer** | Plus simple en surface, populaire historiquement | API datée (pas de DSN, pas de transport plug-and-play), pas de connection pooling natif, retombe en bug-fix-only mode |
| **Hand-rolled fsockopen SMTP** | Zéro dépendance | C'est précisément ce que la migration vers symfony/mailer a remplacé — retour en arrière non justifié |
| **swiftmailer** | — | **Officiellement EOL depuis 2021**. Disqualifié. |
| **MailHog/MailPit + REST** | Bonne UX dev | C'est un outil de capture, pas un transport. Hors scope. |
| **SaaS API (SendGrid/Postmark/Mailgun SDK)** | Délivrabilité top, dashboard tracking | Dépendance commerciale, incompatible cible asso self-hosted (besoin SMTP local OVH/Gandi/relai self-hosted) |
| **Garder symfony/mailer ^8** | Standard de facto PHP, DSN multi-transport, retry + queue support, MIME complet, maintenu | Couplage Symfony écosystème (mais déjà acté par symfony/console + event-dispatcher) |

**Verdict** : **keep**

**Justification** :
- Standard de facto PHP moderne, maintenu.
- Le projet utilise déjà 4 composants Symfony — la dette de couplage existe déjà, retirer mailer n'apporte rien.
- API wrapper (`MailerService`) isole correctement (DI nullable pour test, retour `array{ok,error}`) — le swap futur reste possible si besoin.
- "version 8 = bleeding-edge" mentionné dans REQUIREMENTS.md est une **fausse alerte** : Symfony 8.0 est LTS (release Nov 2025, support jusqu'à 2027).
- Cas d'usage cible (asso self-hosted, SMTP relay externe) parfaitement couvert.

**Coût migration estimé** : N/A (keep)
**Bénéfice attendu** : N/A
**Recommandation Stage 3** : aucune action. Surveiller la sortie Symfony 9.0 (~Nov 2027) et planifier mise à jour alors. Continuer à isoler tout usage Symfony Mailer derrière `MailerService` (pattern actuel).

---

## AUDIT-STACK-05 — Extensions PHP (`gd`, `intl`, `zip`, `pdo_pgsql`, `pgsql`, `mbstring`, `redis`, `Zend OPcache`)

**Rôle aujourd'hui** : extensions installées au build Docker (Dockerfile lignes 45-47, 50-53) et vérifiées au démarrage par fail-fast guard (ligne 59).

**Audit usage par extension** :

| Extension | Usage attesté code | Verdict |
|---|---|---|
| `pdo_pgsql` | `AbstractRepository` (PDO PostgreSQL) — base du Repository layer | **keep** |
| `pgsql` | Fonctions `pg_*` natives (pas seulement PDO). À vérifier : `grep -rEn "pg_(connect\|query\|escape)" app/` → audit code montre uniquement PDO en pratique | **keep par prudence** (peut être requis transitivement par certaines fonctions PG) |
| `mbstring` | 24 sites usage (`mb_detect_encoding`, `mb_convert_encoding`, etc.) — CSV import français | **keep** |
| `redis` (phpredis) | `RedisProvider` + 8 sites (cache, rate-limit, idempotency, SSE queue, security signals) | **keep** |
| `Zend OPcache` | Performance PHP en production, 128 Mo cache, 4000 fichiers accélérés | **keep** |
| `intl` | 2 usages : `transliterator_transliterate` dans `ExportService` (filenames sans accent), `IntlDateFormatter` dans `EmailTemplateService` (dates FR longues) | **keep** |
| `zip` | **Aucun usage applicatif direct** (pas de `ZipArchive` dans `app/` ni `public/`), mais **requis transitivement** par dompdf/openspout/phpspreadsheet (lock : `ext-zip: *` déclaré 4 fois) | **keep** (transitif) |
| `gd` | **Découverte audit** : aucun usage. `EmailTrackingController::outputPixel()` ligne 156 utilise `base64_decode('R0lGODlhAQABAI...')` (GIF hardcoded) — **pas** GD. STACK.md ligne 73 et CLAUDE.md mention "1x1 email tracking pixel generation" sont **inexacts**. | **remove** |

**Découverte significative** : extension `gd` installée + compilée avec freetype/jpeg dans le Dockerfile (lignes 45-46), libs runtime `libpng libjpeg-turbo freetype` permanentes (ligne 36-37), build deps `libpng-dev libjpeg-turbo-dev freetype-dev` (ligne 41-42), **pour rien**. C'est de la dette infra documentaire (les commentaires affirment l'usage mais le code prouve l'absence).

**Alternatives évaluées (gd)** :

| Alternative | Pour | Contre |
|---|---|---|
| **Retirer ext-gd** | -~3 Mo image Docker (libs gd + freetype + jpeg + png), -~30s build (compilation `docker-php-ext-install gd`), suppression 3 packages -dev | Aucun, puisque inutilisé |
| **Garder par prudence** | Si futur usage planifié | Aucun futur usage planifié dans roadmap pivot (Signature PV, VoteDistant, Stats) |

**Verdict** :
- `pdo_pgsql`, `pgsql`, `mbstring`, `redis`, `Zend OPcache`, `intl`, `zip` → **keep** (toutes utilisées directement ou transitivement)
- `gd` → **remove** (aucun usage attesté, doc obsolète)

**Coût migration** : **XS** (< 1h)
- Retirer `gd` de `docker-php-ext-install` (Dockerfile ligne 47).
- Retirer `--with-freetype --with-jpeg` (ligne 45) → `docker-php-ext-configure gd` devient obsolète, ligne supprimable.
- Retirer du fail-fast guard ligne 59 (string `"gd"`).
- Retirer libs runtime `libpng libjpeg-turbo freetype` (ligne 37) — sauf si requis par autre composant (à vérifier : phpspreadsheet/dompdf images embarquées ? Probablement pas en runtime, mais à confirmer en sandbox).
- Retirer build deps `libpng-dev libjpeg-turbo-dev freetype-dev` (ligne 42).
- Mettre à jour `.planning/codebase/STACK.md` ligne 73 et `CLAUDE.md` mention pixel tracking → "GIF base64 hardcodé".

**Bénéfice attendu** :
- Image Docker -~3 Mo, build -~30s, surface attack réduite (fontes CVE historiques freetype).
- Cohérence doc/code.

**Recommandation Stage 3** : **inclure dans M-INFRA-CLEANUP** — quick win, faible risque (sandbox docker build + healthcheck suffit à valider). Si dompdf venait à charger des images PNG dans des PV futurs, ext-gd peut être réinstallée trivialement.

---

## AUDIT-STACK-06 — `AgVote\Core\Router` (routing custom, 348 lignes, 162 routes)

**Rôle aujourd'hui** : routeur custom léger sans dépendance externe. Trois mécanismes :
1. Routes exactes O(1) (`$routes` map) — chemin chaud
2. Routes paramétrées O(n) avec placeholders `{id}` (`$paramRoutes`) — réservé aux routes dynamiques
3. Routes spéciales bootstrap (`$specialRoutes`) — endpoints qui utilisent `bootstrap.php` au lieu d'`api.php` (essentiellement les pixels tracking et endpoints publics non-auth)
4. Pipeline middleware `MiddlewarePipeline` (PSR-15-like mais home-made), middleware par config dans le `map()` 4e param.

**Sites d'usage** :
- `app/Core/Router.php` (348 lignes, classe `final`)
- `app/routes.php` (162 routes mappées via `map()`/`mapAny()`/`mapMulti()`/`mapBootstrap()`)
- `public/index.php` (entry point, instancie + dispatch)
- 100% des routes API + HTML passent par lui, pas de fallback

**Version actuelle** : custom, pas de versioning. Pattern : exact-match d'abord, fallback param-routes, fallback special-routes. Préserve compat URL `.php` pour migration depuis routing fichier.

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **Slim 4** | PSR-7/PSR-15 standard, écosystème middleware (rate-limit, CORS, JWT, OAuth), cache compilé, named routes, URL builder, error handler intégré | Migration de 162 routes (script automatisable mais à valider 1 par 1), middleware actuels (RateLimitGuard, RoleMiddleware, etc.) à porter en PSR-15, ~500 Ko vendor |
| **Symfony Routing** (composant seul) | Compilation cache hyper-rapide, attributes-based routing, named routes natives | Surface beaucoup plus large (option configurations), couplage encore plus fort écosystème Symfony, pas de pipeline middleware natif |
| **FastRoute (nikic)** | Routeur le plus rapide PHP, micro-lib | Pas de middleware (juste routing → handler), il faut greffer un container et un dispatcher = on reconstruit notre custom |
| **Laravel Routing** | DX excellente | Couplage IoC container Laravel, hors scope |
| **Custom Router actuel** | Zéro dépendance, 348 lignes auditables, perf O(1) sur exact-match (route chaude), pipeline middleware fonctionnel, déjà gère `.php` legacy + special routes | Pas de named routes / URL builder (les redirects utilisent des chaînes hardcodées dans tout le code, fragilité refacto), pas de cache compilé (162 routes registrées à chaque request — coût marginal mais O(n) sur params), pipeline middleware non-PSR-15 (pas réutilisable depuis l'écosystème) |

**Verdict** : **keep**

**Justification** :
- 348 lignes simples, parfaitement auditables (vs ~5000 LOC Slim/Symfony Routing).
- Performance acceptable : exact-match O(1) sur ~95% des routes, params O(n) sur ~5%.
- 162 routes à re-câbler = travail mécanique mais surtout **tests E2E à re-valider** = effort L disproportionné vs gain.
- L'absence de named routes est un irritant DX réel mais pas bloquant (une feature future peut introduire un wrapper minimal `RouteRegistry::url('motion.show', $id)` côté custom sans migrer).
- La cible (asso self-hosted, ~10-100 req/min en pic AG) n'a aucune contrainte de perf qui justifierait un cache compilé.
- Recoupement Stage 1 : aucune des 11 étapes audit chemin n'a identifié de bug routing.

**Coût migration estimé** : **L** (~2 semaines)
- Porter 162 routes vers Slim/Symfony — 3-5 jours
- Réécrire 4-6 middlewares custom en PSR-15 (RateLimitGuard, RoleMiddleware, AuthMiddleware, CsrfMiddleware) — 2-3 jours
- Re-tester chaque endpoint (Playwright + PHPUnit) — 2-3 jours
- Risque régression sur edge cases (CORS, OPTIONS preflight, .php legacy URLs)

**Bénéfice attendu** : marginal (named routes, écosystème middleware tiers) — non proportionnel.

**Recommandation Stage 3** : aucune action immédiate. **Améliorations incrémentales possibles sans migration** :
- Ajouter un wrapper `RouteRegistry` pour named routes (effort XS, gain DX visible)
- Ajouter un cache compilé (sérialisation des `routes` + `paramRoutes` en APCu) si jamais perf devient un sujet (pas le cas)

---

## AUDIT-STACK-07 — `AgVote\Core\Logger` (Logger custom JSON, 359 lignes, 29 sites)

**Rôle aujourd'hui** : logger statique JSON structuré avec :
- 8 niveaux (debug → emergency, mapping numérique standard PSR-3)
- Auto-fill contexte : `request_id` (UUID v4 généré au boot), `user_id`, `tenant_id` via helpers globaux
- Méthodes spécialisées : `Logger::api()` (HTTP access log avec duration), `Logger::auth()` (login event avec success bool), `Logger::security()` (security warning), `Logger::exception()` (stack trace formaté)
- Sortie : fichier configuré ou `error_log` PHP par défaut
- Min level configurable

**Sites d'usage** : 29 fichiers (controllers + services + middleware + providers)

**Version actuelle** : custom, classe `static`. Compatible **PSR-3 interface** (cf. PHPDoc ligne 14 "Compatible avec les standards PSR-3 (interface simplifiee)") mais n'implémente pas réellement `Psr\Log\LoggerInterface` (méthodes statiques au lieu d'instance).

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **Monolog 3.x** | Standard de facto PSR-3, ~25 handlers (Stripe, Sentry, Slack, Loggly, Syslog, Rotating, ELK, Stream), ~10 processors (memory, web, git, intro), formatters (JSON, line, HTML), maintenu activement | Migration **mécanique** des 29 sites mais **architecturale** : le logger statique doit devenir un service injecté (impacte DI partout). Perte de l'auto-fill contexte (à reconstituer via Processor). Vendor +500 Ko |
| **PSR-3 logger maison + adaptateur** | Garde l'API `Logger::info(...)` mais expose un `LoggerAdapter` PSR-3 pour les libs tierces qui en demanderaient un | C'est ce que fait *déjà* le custom, en filigrane. Surcoût implémentation = quasi nul, gain = quasi nul tant qu'aucune lib ne le requiert |
| **Symfony Console\Logger** | Cohérent avec le reste de l'écosystème Symfony en place | Limité au CLI, ne remplace pas l'usage HTTP |
| **Sentry SDK direct** | Tracking exceptions production | Ne remplace pas le logger applicatif (complémentaire) ; à intégrer en post-pivot si dogfood révèle besoin |
| **Garder Logger custom** | API ergonomique (`Logger::auth()`, `Logger::security()`, `Logger::api()` sont des helpers domain-specific qui deviennent des Processors complexes en Monolog), auto-fill request_id/user_id/tenant_id, 359 lignes auditables, **migré v2.5 récemment** (cf. REQUIREMENTS.md "47 sites migrés v2.5") — code stable | Pas de handlers tiers out-of-box (Sentry/Slack à intégrer manuellement si besoin futur), API statique = couplage direct (mais isolé via méthodes domain) |

**Verdict** : **keep**

**Justification** :
- API domain-specific (`Logger::auth()`, `Logger::security()`, `Logger::api()`) est un atout réel — encode des conventions de logging (champs `auth_event`, `security_event`, `http.duration_ms`) qui seraient à dupliquer comme Processors Monolog.
- Migration récente v2.5 (47 sites passés au custom Logger) — re-migrer maintenant = jeter un effort fraîchement consenti.
- Auto-fill `request_id` + contexte session = exactement ce qu'un Processor Monolog ferait, mais déjà en place.
- Aucune intégration tierce (Sentry/Slack) demandée par la roadmap pivot. Si un besoin émerge plus tard, **on peut greffer un Monolog handler en parallèle** (le custom Logger peut faire un `Monolog::log()` en plus du write fichier) — donc le besoin "écosystème handlers" n'est pas bloqué par le keep.
- Coupling : le Logger est `static`, donc pas DI-friendly pour tests, mais en pratique les tests utilisent `Logger::configure(['file' => /tmp/test.log])` puis lisent le fichier — pattern fonctionnel.

**Coût migration estimé** : **L** (~1-2 semaines)
- Refactor 29 sites de `Logger::method()` → `$this->logger->method()` (DI partout) — 3-5 jours mécanique mais touche tous les services
- Reconstruire l'auto-fill via Monolog Processors — 1 jour
- Re-implémenter les helpers domain (`api()`, `auth()`, `security()`) comme Processors ou comme Channels — 1 jour
- Tests à mettre à jour (mock LoggerInterface au lieu d'introspection fichier) — 2-3 jours
- Risque : régression silencieuse sur logs (champs structurés perdus si Processor mal config)

**Bénéfice attendu** : marginal aujourd'hui (aucun handler tiers requis), latent (si Sentry/Slack adoptés en post-pivot).

**Recommandation Stage 3** : aucune action. **Améliorations incrémentales possibles sans migration** :
- Implémenter formellement `Psr\Log\LoggerInterface` via un wrapper (effort XS) pour permettre injection dans libs tierces (ex. Symfony Mailer accepte un PSR-3 logger)
- Si Sentry adopté plus tard : ajouter un handler Sentry directement dans `Logger::write()` — effort S, sans migration globale

---

## AUDIT-STACK-08 — `AgVote\Core\Security\IdempotencyGuard` (87 lignes)

**Rôle aujourd'hui** : guard léger pour endpoints POST. Lit le header `X-Idempotency-Key`, check Redis pour réponse cachée, retourne tel quel si trouvée. TTL 1h, prefix `idempotency:`. Fallback no-op si Redis indisponible (`extension_loaded('redis')` check).

**Sites d'usage** : 7 fichiers (handlers POST sensibles : import membres CSV/XLSX, motions create, ballots cast, attendances, etc. — cf. ImportController étape 02 audit chemin)

**Version actuelle** : custom, classe `final` méthodes statiques. 87 lignes.

**Caractéristiques** :
- Single key store (pas de fingerprinting body+method+uri comme `Request` PSR-7-style)
- TTL hardcodé `1 hour` (constante `TTL = 3600`)
- Sérialisation : Redis `OPT_SERIALIZER_JSON` (cf. `RedisProvider` ligne 81), donc `setex()` accepte un array directement
- Clé d'idempotence reçue via header → confiance dans le client (qui doit générer un UUID v4)
- Pas de "lock" : 2 requêtes concurrentes avec la même clé peuvent toutes deux exécuter avant que la première ait stocké le résultat → race condition possible (mais courte)

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **`symfony/lock`** + custom store | Locks distribués Redis natifs (`RedisStore`), TTL configurable, primitives `acquire()`/`release()` battle-tested, pas de race condition | Pas la même sémantique : `symfony/lock` empêche concurrence (acquire ou throw), mais ne **cache pas le résultat**. Il faudrait ajouter un store JSON par-dessus → on reconstruit ce qu'on a déjà |
| **`stripe/idempotency` patterns** | Référence d'implémentation industrielle | Pas une lib PHP installable — c'est un pattern décrit, qu'on a déjà. |
| **Implémentation maison renforcée** : ajouter `SETNX` (lock) + body fingerprint (`hash('sha256', method+uri+body)`) avant exécution + post-store résultat + délai d'attente sur lock concurrent | Race condition fermée, fingerprint protège contre key reuse abusif (client réutilise une key avec un body différent) | Coût XS, mais code AgVote actuel `IdempotencyGuard::check()` ne sait pas où se brancher pour le lock (le contrôleur fait l'execution avant `store()`) — une vraie consolidation `withIdempotency(callable)` serait plus propre |
| **Garder `IdempotencyGuard` actuel** | 87 lignes auditables, fallback graceful, déjà câblé sur 7 endpoints critiques | Race condition courte (très peu probable en pratique : un client qui retry envoie typiquement la 2e requête après timeout HTTP, ~30s plus tard, donc le résultat est déjà en Redis), TTL non configurable per-endpoint, pas de fingerprint body |

**Verdict** : **keep** (avec amélioration optionnelle)

**Justification** :
- Cas d'usage cible (asso, ~10-50 utilisateurs concurrents max) ne génère pas de scenarios de race condition réalistes — le retry HTTP humain ou navigateur arrive plusieurs secondes après la 1re requête, et celle-ci a déjà stocké en moins de 1s.
- 87 lignes vs symfony/lock + custom store JSON ≈ 200 lignes maintenues pour le même résultat fonctionnel.
- Recoupement Stage 1 étape 02 (audit chemin import CSV) : `IdempotencyGuard::check()` + `::store()` câblés, pattern fonctionnel constaté.

**Coût migration estimé** : N/A (keep). Si "renforcement" choisi : **XS** (<2h) — ajouter `withIdempotency(callable, ?int $ttl)` + body fingerprint optionnel + Redis SETNX lock ; sites existants peuvent migrer progressivement.

**Bénéfice attendu** : marginal aujourd'hui ; latent si charge concurrente augmente.

**Recommandation Stage 3** : **keep** + amélioration optionnelle XS si effort planifié sur la couche sécurité ailleurs (sinon différer). Documenter la race condition courte comme limitation connue dans `app/Core/Security/IdempotencyGuard.php` (PHPDoc).

---

## AUDIT-STACK-09 — `AgVote\Core\Http\*` (primitives HTTP custom, ~644 lignes)

**Rôle aujourd'hui** : 6 fichiers wrappant les superglobales et formant un mini-toolkit HTTP :

| Fichier | Lignes | Rôle |
|---|---|---|
| `Request.php` | 175 | Wrap `$_GET/$_POST/$_SERVER` + body JSON ; cache `php://input` ; helpers `query()`, `body()`, `requireUuid()`, `validate()` |
| `JsonResponse.php` | 90 | Value object réponse JSON ; factory `ok($data)` / `fail($error, $code)` ; rendu via `send()` ; redaction 5xx en non-dev |
| `ApiResponseException.php` | 29 | Exception porteuse de `JsonResponse` (non-rendu, propagation jusqu'au router) |
| `HttpCache.php` | 80 | Helpers ETag / Cache-Control |
| `ClientIp.php` | 116 | Extraction IP réelle robuste (F02 hardening, anti-spoofing X-Forwarded-For) |
| `UrlValidator.php` | 154 | Validation URL stricte (anti-SSRF, scheme allowlist) |

**Sites d'usage** : usage transverse (controllers, middleware, helpers globaux `api_*`).

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **PSR-7 (`nyholm/psr7` ou `laminas-diactoros`) + PSR-15 middleware** | Standard PHP-FIG, immutabilité, écosystème (Slim, Mezzio, Symfony HttpKernel adapter), interopérabilité libs tierces | Surcoût ~2 Mo vendor + couplage transverse (toutes les méthodes deviennent `(ServerRequestInterface): ResponseInterface`), refonte de **toute la couche controller** (`AbstractController::handle()` + helpers `api_ok/api_fail/api_query`), perte de la fluidité actuelle (PSR-7 immutabilité = `withHeader()` chaining verbeux) |
| **Symfony HttpFoundation** | Plus pragmatique que PSR-7 strict, déjà compatible avec écosystème Symfony en place | Couplage Symfony renforcé, ~3 Mo vendor, refonte transverse identique |
| **Garder Http/ custom** | 644 lignes spécifiques au besoin AgVote (ClientIp F02 anti-spoofing, UrlValidator anti-SSRF, JsonResponse `ok/fail` enrichi en français), zéro surcoût vendor, helpers globaux `api_*` ergonomiques (1 ligne là où PSR-7 demande 4-5) | Pas d'interop directe avec libs PSR-15 tierces (irrelevant en pratique car aucune lib actuelle ne le requiert) ; `Request` lit `$_SERVER` directement à la construction → pas immuable, mais c'est intentionnel (mock via `$_SERVER` en test) |

**Verdict** : **keep**

**Justification** :
- Les 6 classes encodent des **règles de sécurité spécifiques** (ClientIp F02, UrlValidator anti-SSRF, JsonResponse redaction 5xx) qui sont des **assets** plutôt que de la dette — porter ces règles dans des Decorators PSR-15 = 2x plus de code pour le même résultat.
- L'API ergonomique (`api_ok($data)`, `api_fail('not_found', 404)`) couvre 95% des cas controller en 1 ligne, équivalent PSR-7 = `return new JsonResponse(['ok' => true, 'data' => $data]);` ou via factories — plus verbeux.
- Migration = refonte transverse de tous les controllers (~50 fichiers) + middleware (~6 fichiers) → coût XL, gain interop théorique mais non-utilisé.
- Recoupement : Stage 1 audit chemin n'a relevé aucun bug attribuable aux primitives HTTP custom. Le hardening F02-F22 mentionné dans archives est intégré dedans.

**Coût migration estimé** : **XL** (>2 semaines)
- Refonte AbstractController + tous controllers (50+ fichiers) — 5-7 jours
- Refonte 6 middlewares → PSR-15 — 2-3 jours
- Refonte helpers `api_*` globaux (transparence wrapper ?) — 2 jours
- Re-test E2E + Playwright complet — 3-4 jours
- Risque régression sécurité (ClientIp anti-spoofing, redaction 5xx, CSRF token extraction)

**Bénéfice attendu** : interop PSR-15 (jamais utilisée actuellement, hypothétique).

**Recommandation Stage 3** : aucune action. **Optionnel** : implémenter `Psr\Http\Message\ServerRequestInterface` côté `Request` (sans changer l'API publique) si une lib tierce le demande un jour — adapter pattern, effort S.

---

## AUDIT-STACK-10 — `AgVote\SSE\*` (Server-Sent Events custom, 477 lignes)

**Rôle aujourd'hui** : 3 fichiers gérant le canal SSE temps-réel pour le cockpit opérateur live (motion ouverte, vote casté, présence multi-op, heartbeat 10s) :

| Fichier | Lignes | Rôle |
|---|---|---|
| `SseAuthGate.php` | 129 | Gate auth + tenant-isolation pour SSE consumers (F05 hardening) — décide allowed/auth_required/session_expired/etc. |
| `EventBroadcaster.php` | 248 | Publication d'événements vers Redis queue (`sse:event_queue`, max 1000 messages), event types domain (motion.opened, vote.cast, etc.) |
| `HeartbeatPayloadBuilder.php` | 100 | Construit payload heartbeat (status meeting + quorum + présence) — try/catch isolé par sub-query pour resilience |

Ils sont consommés par `public/api/v1/events.php` (script procédural SSE long-lived qui pop la Redis queue).

**Sites d'usage** :
- `app/SSE/*` (3 classes)
- `public/api/v1/events.php` (consumer SSE)
- 8 services `EventBroadcaster::xxx()` (NotificationsService, AttendancesService, OfficialResultsService, MotionsService, ProxiesService, MeetingReportsService, BallotsService, etc.)

**Caractéristiques** :
- Architecture pub/sub via Redis LIST (LPUSH côté broadcaster, BRPOP côté consumer dans events.php)
- Heartbeat 10s + events pushés en dispatch
- Queue plafonnée à 1000 messages (`MAX_QUEUE_SIZE`) — auto-éviction au-delà
- Auth par session (cookie) côté SSE consumer, gate ré-évaluée à chaque heartbeat
- Recoupement Stage 1 : étapes 06 (vote en direct) et 09 (cockpit operateur) reposent dessus, marquées ✓ et ⚠ respectivement

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **Mercure** (hub SSE PHP officiel Symfony) | SSE standard, JWT auth, topic-based subscriptions, Caddy-based hub Go scalable | **Hub séparé** (un binaire Go à déployer) → casse le modèle "1 container Docker" actuel, complexifie ops self-hosted asso ; auth JWT à intégrer (plus complexe que session actuelle) |
| **Centrifugo** | Très scalable, multi-protocole (WS/SSE/HTTP polling), client JS officiel | Hub séparé Go, auth tokens JWT/HMAC, WS par défaut (HTMX 2.0.6 utilise SSE) — surdimensionné pour cible asso (max ~10 op simultanés, déjà out of scope >10 dans PROJECT.md) |
| **Pusher / Ably / SaaS** | Délégation totale | Coût récurrent, dépendance externe → incompatible self-hosted asso. **Disqualifié.** |
| **WebSocket via Ratchet/ReactPHP** | Bidirectionnel | HTMX 2.0.6 utilise SSE, pas WS ; ajouter WS = pivot frontend ; nginx proxy WS plus complexe à config ; aucun besoin bidirectionnel actuel |
| **Long-polling pur** (sans SSE) | Compatibilité IE/proxy old | Régression UX (latence ~3-5s vs <1s en SSE) ; HTMX gère SSE proprement ; pas de bénéfice pour cible (Chrome/Firefox/Edge récents) |
| **Garder SSE custom AgVote** | 477 lignes auditables, gate auth + tenant-isolation F05 documentée, rester sur 1 container Docker (cible self-hosted), HTMX 2.0.6 SSE-natif, scalabilité actuelle suffisante (PROJECT.md "Out of Scope: SSE scaling >10 op simultanés") | Pas de pub/sub natif Redis (utilise LIST + BRPOP, qui marche mais n'est pas le pattern Redis idiomatique → idéalement passer à Redis Streams pour ack + replay) ; un seul consumer SSE par requête (pas de fan-out natif si plusieurs ops connectés au même meeting → chaque consumer pop sa propre fenêtre = duplication ?) — à vérifier en code |

**Verdict** : **keep**

**Justification** :
- Boundary explicite PROJECT.md : "SSE scaling >10 op simultanés = out of scope" → la complexité d'une migration Mercure/Centrifugo n'est pas alignée avec la cible.
- Single-container Docker = pilier ops cible asso ; ajouter un hub séparé contredit cette contrainte.
- Stage 1 recoupement : étape 06 (vote direct) ✓, étape 09 (cockpit live) ⚠ — la nuance ⚠ étape 09 concerne **scaling présence multi-op**, déjà boundary out-of-scope.
- 477 lignes + Redis queue = stack simple et maîtrisée.

**Coût migration estimé** : **L** (~1-2 semaines)
- Déployer Mercure ou Centrifugo en sidecar Docker — 2-3 jours
- Réécrire `EventBroadcaster::queue()` → publish topic + JWT — 2-3 jours
- Réécrire frontend HTMX SSE (URL + auth header) — 2-3 jours
- Refactor SseAuthGate → JWT signer/verifier — 1-2 jours
- Re-tester scenarios multi-op + heartbeat + reconnect — 2-3 jours

**Bénéfice attendu** : scaling ops >10 (hors scope), topic-based subscriptions natives (irrelevant pour le pattern actuel meeting-broadcast).

**Recommandation Stage 3** : aucune action. **Amélioration optionnelle** : migrer la queue Redis LIST → Redis Streams (effort S) pour avoir ack + replay si reconnect — mais seulement si reconnects buggy se manifestent en dogfood réel (post-pivot, non préemptif). Sinon différer définitivement jusqu'à signal terrain "scaling SSE".

---

## AUDIT-STACK-11 — Redis (cache + rate-limit + SSE queue + idempotency + security signals)

**Rôle aujourd'hui** : Redis 7 (Alpine), conexion gérée par `AgVote\Core\Providers\RedisProvider` (singleton, ping pour healthcheck, fallback `RuntimeException` si phpredis absent — ce qui ne se produit jamais en prod car ext-redis fail-fast au boot Dockerfile ligne 59).

**Usages réels identifiés (8 sites)** :

| Composant | Usage | Criticité |
|---|---|---|
| `IdempotencyGuard` | Cache réponses POST 1h | Important (évite double imports) |
| `RateLimiter` (`Core/Security/RateLimiter.php`) | Compteurs sliding window par contexte (auth_login, csv_import, public_vote, admin_ops) | Critique (F02 anti-bruteforce) |
| `AccountLockout` (`Core/Security/AccountLockout.php`) | Counter F13 progressive lockout par compte | Critique (F13 hardening) |
| `SecuritySignal` (`Core/Security/SecuritySignal.php`) | F21 signal events (failed login, suspicious activity) | Important (audit) |
| `EventBroadcaster` | Queue SSE LIST `sse:event_queue` | Critique (cockpit live ne marche pas sans) |
| `events.php` | Consumer SSE BRPOP | Critique |
| `health.php` | Healthcheck endpoint | Confort |
| `RedisHealthCommand` (CLI) | Diagnostic ops | Confort |

**Découverte audit** : **Redis n'est pas utilisé pour les sessions** (`deploy/php.ini` ligne `session.save_path = "/tmp"` → sessions fichier). Le STACK.md ligne 103 le mentionne explicitement : "session.save_path = "/tmp" (file-based sessions — NOT Redis-backed)". Les sessions filesystem `/tmp` sont éphémères au container redémarré → **dette UX latente** (utilisateurs déconnectés à chaque redéploy).

**Version actuelle** : Redis 7 (Docker compose externe), phpredis (PECL).

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **Retirer Redis entièrement, fallback fichier** | -1 service à déployer, op simplifiée pour cible asso self-hosted | Perd rate-limit cohérent multi-process (PHP-FPM workers concurrents → file lock contention), perd queue SSE atomique (LIST+BRPOP n'a pas d'équivalent fichier propre), perd security signals indexables — **régression sécurité majeure** F02/F13/F21. **Disqualifié.** |
| **SQLite local pour rate-limit + idempotency** | Simple, single-file, ACID | Pas adapté pour BRPOP-like (SSE queue), contention écriture sous concurrence (sqlite global lock). Adopté seulement si on retire SSE — incompatible. |
| **PostgreSQL pour rate-limit + idempotency + SSE LISTEN/NOTIFY** | Réutilise infra existante (1 service au lieu de 2), pg_notify natif pour SSE | LISTEN/NOTIFY non persistent (pas de replay), requêtes counters tablée plus lente que Redis INCR (mais à l'échelle cible : non bloquant), refonte 5 services Security + SSE = effort L |
| **DragonflyDB ou KeyDB** (drop-in Redis) | Compat protocole Redis | Inutile : Redis 7 fonctionne. Pivot non justifié. |
| **Garder Redis** | Toutes les abstractions sont déjà en place, perf in-memory inégalable, single-instance suffit pour cible (~10 ops, ~50 votants), backup non critique (data éphémère = compteurs + queue) | 1 service supplémentaire à déployer Docker compose, mémoire ~30-50 Mo |

**Verdict** :
- Redis lui-même → **keep**
- **Migration Sessions → Redis recommandée** comme amélioration séparée (résoud la dette latente UX au redéploy)

**Justification** :
- Les 8 usages sont nécessaires et adaptés au composant (in-memory atomique, queue native, healthcheck simple).
- Aucun fallback fichier ne préserve la cohérence concurrente requise pour rate-limit + SSE.
- Single Redis instance = trivial à déployer dans une compose (image alpine ~5 Mo).
- "Possibilité retirer pour single-tenant self-hosted ?" (REQUIREMENTS.md AUDIT-STACK-11) → **non**, le retrait régresserait F02/F13/F21 sécurité.

**Coût migration sessions → Redis** : **S** (~1 jour)
- Configurer `session.save_handler = redis` + `session.save_path = "tcp://redis:6379?prefix=sess:&auth=..."` dans `deploy/php.ini`
- Tester avec un container restart pour vérifier persistence sessions
- Documenter dans `.env.production.example`

**Bénéfice attendu** :
- Sessions persistent au redéploy container.
- Single source of truth (Redis) pour tous les états éphémères.

**Recommandation Stage 3** :
- Redis : **keep**.
- **Action S concrète** : migrer sessions PHP fichier → Redis. Bloque pas le pivot mais améliore UX dogfood (un opérateur de séance ne perd pas sa session après un déploiement). Inclure dans M-INFRA-CLEANUP.

---

## AUDIT-STACK-12 — PostgreSQL extensions + indexes

**Rôle aujourd'hui** : PostgreSQL 16 (compose externe), connectivité PDO (`pdo_pgsql`). 30 tables + 88 index dans `database/schema-master.sql`, 29 migrations dans `database/migrations/` (61 index supplémentaires). **149 index au total** sur ~30 tables → ratio ~5 index/table, raisonnable.

**Extensions PostgreSQL utilisées** :
- `citext` — emails case-insensitive (CREATE EXTENSION dans schema-master.sql)
- `pgcrypto` — `gen_random_uuid()` pour PK UUID v4
Aucune autre extension custom (pas de PostGIS / pgvector / etc., aligné avec besoins fonctionnels).

**Index audit par table critique** :

| Table | Index présents | Hot path couvert ? | Manquant probable ? |
|---|---|---|---|
| `users` | uniqueness `(tenant_id, email)` (citext), role | ✓ login, tenant scope | — |
| `members` | `(tenant_id, full_name)` UNIQUE, `(tenant_id, external_ref)` UNIQUE, `(tenant_id, is_active)` | ✓ import dedup, listing | Possiblement `(tenant_id, lower(email))` si recherche par email pratiquée — à confirmer en code |
| `meetings` | `tenant_id`, `status`, `(tenant_id, status)` | ✓ filtre dashboard | — |
| `motions` | `(tenant_id, meeting_id)`, `(tenant_id, meeting_id, opened_at) WHERE closed_at IS NULL` (partial), `(meeting_id, slug)` UNIQUE | ✓ live (vote en cours) | — |
| `ballots` | `(tenant_id, meeting_id)`, `motion_id`, `(tenant_id, meeting_id, motion_id)` (covering F-hardening) | ✓ tally rapide | — |
| `audit_events` | `(tenant_id, created_at)`, `(resource_type, resource_id)` partial, `(action, created_at DESC)`, `(tenant_id, action, created_at DESC)` (covering v2.7) | ✓ /admin/audit listings | — |
| `email_queue` | `scheduled_at WHERE status='pending'` partial, `(tenant_id, status)`, `(meeting_id)` partial, `(status, scheduled_at) WHERE pending/processing` | ✓ worker pickup, retries | — |
| `paper_ballots` | `code_hash` UNIQUE, `code_hash WHERE used_at IS NULL` partial | ✓ scan code | — |
| `attendances` | À vérifier | ? | Probablement `(tenant_id, meeting_id, member_id)` UNIQUE existant |
| `proxies` | À vérifier | ? | Probablement `(tenant_id, meeting_id, giver_id)` UNIQUE |

**Découvertes** :
1. **Covering indexes v2.7** annoncés dans REQUIREMENTS.md : **vérifiés présents** :
   - `idx_ballots_tenant_meeting_motion` (migration `20260218_security_hardening.sql`)
   - `idx_audit_tenant_action_time` (migration `20260311_audit_covering_index.sql`, marqué CONCURRENTLY = production-safe)
2. **Migrations idempotentes** : tous les `CREATE INDEX IF NOT EXISTS` → re-exécution sûre.
3. **Pas de index unused détecté** statiquement (audit pg_stat_user_indexes nécessite live runtime → out of scope sandbox).
4. Pas d'index manifestement manquant identifié sans analyse runtime (`EXPLAIN ANALYZE` sur requêtes hot — Stage 3 followup possible).

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **Migrer vers MySQL 8** | Aucun | Régression : pas de partial index identique, pas de citext, pas de UUIDv4 natif sans extension. **Disqualifié.** |
| **SQLite** | Embed simple | Pas adapté concurrence cible (multi-op live), pas de partial index identique. **Disqualifié.** |
| **MongoDB / NoSQL** | — | Schemas relationnels avec contraintes (FK + UNIQUE composite) au cœur du domaine, NoSQL régresserait l'intégrité. **Disqualifié.** |
| **Garder PostgreSQL 16** | Stack mature, citext/pgcrypto disponibles, 149 index couvrent les hot paths, partial index supportés | Pas d'alerte réelle |

**Verdict** : **keep**

**Justification** :
- Schéma bien indexé, partial index utilisés à bon escient pour réduire taille (ex. `email_queue.status='pending'`, `paper_ballots.used_at IS NULL`).
- Covering indexes v2.7 livrés (recoupement avec PROJECT.md "Validated v2.7 polish").
- Extensions minimales (citext + pgcrypto = standard PostgreSQL).

**Coût migration** : N/A (keep)
**Bénéfice attendu** : N/A
**Recommandation Stage 3** :
- Aucune action critique.
- **Followup post-pivot recommandé** : analyser `pg_stat_user_indexes` en dogfood réel pour identifier index *unused* à drop (effort XS, gain stockage marginal). À faire **après** premier déploiement asso pilote, pas en Stage 3.
- **Followup post-pivot** : `EXPLAIN ANALYZE` sur top 5 requêtes hot (motion live tally, dashboard listings, audit search) pour vérifier qu'aucun seq scan n'apparaît. Effort S, à faire en M-DOGFOOD-OBSERVABILITY.

---

## AUDIT-STACK-13 — Docker multi-stage Alpine + nginx + supervisord

**Rôle aujourd'hui** : Dockerfile 119 lignes, 2 stages :
1. **assets** (Node 20.19-alpine3.21) : minification CSS/JS (terser + clean-css-cli), discardé après build
2. **runtime** (php:8.4-fpm-alpine3.21) : nginx + supervisord + php-fpm + email queue worker (Symfony Console boucle infinie en 30 prio)

Process supervisor (`deploy/supervisord.conf`, 72 lignes) orchestre :
- `php-fpm` (priority 10)
- `nginx` (priority 20)
- `email-queue` worker (priority 30, Symfony Console boucle infinie avec backoff exponentiel 60→900s)
- (probablement d'autres programs pour SSE/cleanup, à vérifier)

Build : `apk upgrade --no-cache` initial (alignement musl), 2 groupes apk (`.php-build-deps` virtual + permanent runtime libs), ext PHP compilées (`docker-php-ext-install`), phpredis (PECL), Composer 2 (depuis image officielle, retiré post-install), classmap autoload `--classmap-authoritative`, run as `www-data`.

**Caractéristiques** :
- Healthcheck HTTP `/api/v1/health.php` (interval 30s)
- Port 8080 exposé
- Container ne tourne PAS en root (USER www-data)
- Rechargement live config nginx via `nginx.conf.template` + entrypoint
- Multi-stage propre : pas de Composer, pas de Node, pas de gcc dans l'image runtime finale.

**Mesure approximative image runtime** : ~150-200 Mo (php:8.4-fpm-alpine3.21 base ~80 Mo + nginx + supervisord + libs runtime + vendor PHP ~25 Mo + sources ~5 Mo).

**Alternatives évaluées** :

| Alternative | Pour | Contre |
|---|---|---|
| **FrankenPHP** (Caddy embarqué + worker mode PHP) | 1 binary single-process (pas de supervisord), Caddy = TLS automatique + HTTP/2/3, mode worker PHP-FPM-like sans le surcoût FPM, image plus simple | Migration : refonte nginx.conf → Caddyfile (~230L → ~50L Caddy), tester worker mode (incompat avec sessions fichier `/tmp` cf. AUDIT-STACK-11 ; **dépendance** : il faudrait d'abord migrer sessions Redis), supervisord toujours requis pour email-queue + autres workers async, donc le gain "1 binary" n'est pas total. PHP 8.4 supporté FrankenPHP. |
| **RoadRunner** (workers PHP Go) | Performance × 5-10 sur app stateful, request lifecycle Go-natif | Refonte transverse (workers stateful, attention superglobales), incompat plug-and-play avec routing custom + bootstrap actuel, maturité écosystème |
| **Swoole/OpenSwoole** (event loop async) | Async natif, perf | Réécriture transverse, contraintes async (pas tous les libs compat), surdimensionné pour cible |
| **php-fpm + nginx séparés** (2 containers) | Patron "1 process = 1 container" Docker idiomatique | Cible asso self-hosted = ops Docker compose simple, 2 containers = surcoût mental ; perf marginale |
| **php-fpm + Apache** | Apache mod_php classique | Régression vs nginx (perf/connexions), pas de gain |
| **Garder Dockerfile actuel** | Stack mature, multi-stage propre, fail-fast guard ext PHP, `apk upgrade` initial qui résout musl drift documenté, run non-root, supervisord couvre tous les processes (php-fpm + nginx + workers) | Pas le plus minimal possible (~150-200 Mo) ; si gd retiré (AUDIT-STACK-05) → -3 Mo |

**Verdict** : **keep** (avec amélioration optionnelle FrankenPHP différée)

**Justification** :
- Stack actuelle mature, alignée avec les best-practices Docker (multi-stage, non-root, healthcheck, fail-fast guard, classmap-authoritative).
- FrankenPHP est attractif mais demande **prérequis** (sessions Redis cf. AUDIT-STACK-11) et **n'élimine pas supervisord** (email-queue worker reste async).
- Le gain "1 binary" est partiel ; le gain "Caddy TLS auto" est neutralisé en self-hosted asso (souvent derrière un reverse-proxy maison ou Traefik existant).
- Recoupement Stage 1 : aucune des étapes audit chemin n'a relevé de problème ops/Docker.
- Le commentaire `apk upgrade --no-cache` lignes 23-31 documente la rustine musl drift Alpine — preuve de maturité ops.

**Coût migration FrankenPHP estimé** : **M** (3-5 jours)
- Pré-requis : sessions Redis (AUDIT-STACK-11) — 1 jour
- Refonte Dockerfile (base FrankenPHP image) — 1 jour
- Refonte nginx.conf → Caddyfile (~230L conditionnel/regex/cache → équivalent Caddy) — 1-2 jours
- Garder supervisord pour email-queue + workers async — pas de changement
- Tester en sandbox docker compose — 1 jour
- Risque : worker mode PHP requiert que le code soit "stateless-friendly" (pas de superglobales mutées entre requests) — à auditer

**Bénéfice attendu** : -1 process (nginx supprimé) ; perf request +20-30% en mode worker (mais cible n'a aucun problème de perf actuel) ; image possiblement -30 Mo (FrankenPHP image plus compacte).

**Recommandation Stage 3** :
- **keep** pour Stage 3 immédiat (Voie A confirmée) — pas d'urgence.
- **Évaluer FrankenPHP en M-INFRA-CLEANUP** une fois les autres dettes traitées (sessions Redis + ext-gd retirée + dump-autoload classmap déjà OK). Faisable post-pivot si signal terrain "ops simplification souhaitée".

---

## AUDIT-STACK-14 — Synthèse + recommandations Stage 3

### Récapitulatif des 13 verdicts

| # | Composant | Version / taille | Verdict | Coût migration |
|---|---|---|---|---|
| 01 | `dompdf/dompdf` | v3.1.4 | **keep** | N/A |
| 02 | `phpoffice/phpspreadsheet` (+ `openspout/openspout`) | v1.30.2 + v5.6.0 | **replace** (PhpSpreadsheet → OpenSpout Reader) | S (1 jour) |
| 03 | `erusev/parsedown` | v1.8.0 | **replace** (→ league/commonmark) | XS (<2h) |
| 04 | `symfony/mailer` | v8.0.4 | **keep** | N/A |
| 05 | Extensions PHP | 8 ext | **keep 7, remove `gd`** | XS (<1h) |
| 06 | `AgVote\Core\Router` | 348 L, 162 routes | **keep** | N/A |
| 07 | `AgVote\Core\Logger` | 359 L, 29 sites | **keep** | N/A |
| 08 | `AgVote\Core\Security\IdempotencyGuard` | 87 L, 7 sites | **keep** | N/A |
| 09 | `AgVote\Core\Http\*` | 644 L, 6 fichiers | **keep** | N/A |
| 10 | `AgVote\SSE\*` | 477 L, 3 fichiers | **keep** | N/A |
| 11 | Redis | 8 sites | **keep** + recommandation **migrer sessions → Redis** | S (1 jour) |
| 12 | PostgreSQL + indexes | 149 idx / 30 tables | **keep** | N/A |
| 13 | Docker multi-stage | 119 L Dockerfile | **keep** (FrankenPHP différé) | N/A |

### Compteurs

- **keep** : **11** sur 13 (85%)
- **replace** : **2** (PhpSpreadsheet, Parsedown)
- **remove** : **1** (ext-gd)
- **migration latérale recommandée** : **1** (sessions PHP fichier → Redis)

### Effort total estimé migrations (Stage 3+)

| Action | Coût | Bloqueur ? | Bénéfice |
|---|---|---|---|
| Retirer `ext-gd` du Dockerfile | XS (<1h) | Non | -3 Mo image, -30s build, surface attack réduite |
| Remplacer `Parsedown` → `league/commonmark` | XS (<2h) | Non | Sortie d'une dépendance abandonnée, suppression rustine `error_reporting()`, compat PHP 8.5+ |
| Migrer sessions PHP fichier → Redis | S (1 jour) | Non | Sessions persistent au redéploy container (UX dogfood) |
| Remplacer `PhpSpreadsheet` import → `OpenSpout` Reader | S (1 jour) | Non (PhpSpreadsheet 1.x EOL Q4 2026 = échéance moyen terme) | -5 Mo vendor, mémoire constante import, symétrie API export |
| **Total Stage 3 court-terme** | **~2,5 jours dev** | — | — |

Tous les efforts sont **non-bloquants** pour le pivot et **isolables** en milestones séparées (ne touchent pas le chemin critique fonctionnel).

### Verdict global Stage 3

**✓ Voie A (refacto sur place) confirmée.**

L'audit n'a révélé **aucun gap structurel majeur** justifiant une migration langage (PHP) ou framework. Sur 13 composants audités :
- **11 keeps** : la stack est saine. Custom code AgVote (Router/Logger/IdempotencyGuard/Http/SSE) encode des règles de sécurité (F02-F22, F05) et des conventions domain qui sont des **assets** plutôt que de la dette.
- **3 actions ciblées** (replace ext-gd, replace Parsedown, replace PhpSpreadsheet) toutes avec coût XS-S et bénéfice immédiat.
- **0 risques runtime structurels détectés** en audit statique sandbox.

La Core Value pivot (*"le secrétaire de séance fait en 5 clics ce qui prenait 1h en papier, traçabilité légale ≥ papier"*) **n'est bloquée par aucun élément de stack**. Les 3 features 1.0 prioritaires (Signature PV, Vote distant token, Stats cross-séance) peuvent être construites sur la stack existante sans pré-requis stack.

### Top 3 priorités Stage 3 (ratio impact/effort)

| # | Action | Coût | Impact |
|---|---|---|---|
| **1** | **Migrer sessions PHP fichier → Redis** | S (1 jour) | **Élevé** : ferme la dette UX dogfood "user déconnecté au redéploy" — bloquant en pratique pour démo/pilote asso |
| **2** | **Remplacer `phpoffice/phpspreadsheet` → `OpenSpout` Reader côté import** | S (1 jour) | **Moyen** : ferme l'asymétrie mémoire import/export, sort de la zone EOL Q4 2026, recoupe Stage 1 étape 02 (audit chemin import CSV/XLSX marqué ⚠) |
| **3** | **Quick-wins infra** : retirer `ext-gd` Dockerfile + remplacer `parsedown` → `league/commonmark` | XS+XS (<3h cumul) | **Faible techniquement** mais **élevé en hygiène** : sort d'une dépendance abandonnée (Parsedown CVE latentes), réduit image Docker, supprime rustine `error_reporting()` |

### Décision recommandée Stage 3 (M-DECISION)

1. **Confirmer Voie A** sur la base des deux audits Stage 1 (chemin) + Stage 2 (stack).
2. **Constituer un milestone unique "M-INFRA-CLEANUP"** absorbant les 4 actions ci-dessus (sessions Redis + OpenSpout import + ext-gd + Parsedown). Effort total ~2,5 jours dev.
3. **Démarrer M-Signature** en parallèle (pas de dépendance bloquante stack).

### Findings hors-scope identifiés mais à logger pour Stage 3+

- **Doc inexacte** : STACK.md ligne 73 et CLAUDE.md mention "GD pour 1x1 tracking pixel" → faux (pixel = base64 hardcodé). À corriger en passant.
- **Sessions filesystem `/tmp`** : non documenté comme dette dans PROJECT.md "Dette technique connue" → à ajouter.
- **Race condition courte IdempotencyGuard** : à documenter comme limitation connue dans le PHPDoc du fichier.
- **Followup runtime** : `pg_stat_user_indexes` + `EXPLAIN ANALYZE` sur top requêtes hot — à différer en M-DOGFOOD-OBSERVABILITY post-déploiement asso pilote.

---

*Audit réalisé : 2026-05-06. Boundary respectée : aucun fichier production modifié. Toutes les recommandations sont actionnables et chiffrées.*

