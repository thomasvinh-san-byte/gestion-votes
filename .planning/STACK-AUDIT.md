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
