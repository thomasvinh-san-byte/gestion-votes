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
