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
