# Milestones

> **Pivot stratégique 2026-05-05** : Le projet a été reset post-v2.7. L'historique technique des 28 milestones précédents (v1.0 → v2.7) est archivé dans `.planning/archive-pre-pivot-2026-05-05/MILESTONES.md` pour référence.
>
> Post-pivot, les milestones sont nommés par feature/audit (M-AUDIT-CHEMIN, M-Signature, etc.) plutôt que par version semver.

## M-AUDIT-STACK — Audit stack technique (Shipped 2026-05-05)

**Stage 2 du pivot stratégique radical post-v2.7.**

**Plans:** 1 plan (01-01), 14 tasks atomiques, 16 commits
**Output:** `.planning/STACK-AUDIT.md` (722 lignes, 13 audits + synthèse)
**Score:** 11 keep / 2 replace / 1 remove sur 13 composants
**Verdict global:** **Voie A (refacto sur place) confirmée** — pas de rebuild infra. Effort court-terme ~2.5j dev.
**Boundary:** PASS — git status sur production paths vide

**Top 3 priorités Stage 3 :**
1. Sessions PHP fichier → Redis (S, 1j) — bloquant UX dogfood
2. PhpSpreadsheet → OpenSpout import (S, 1j) — symétrie XLSX + EOL
3. Quick-wins : ext-gd remove + Parsedown → CommonMark (XS+XS)

**Découvertes :** doc CLAUDE.md inexacte (GD pas utilisé), sessions /tmp non documentée comme dette, stack XLSX asymétrique.

**Archived:** `.planning/milestones/M-AUDIT-STACK-MILESTONE-AUDIT.md` + `M-AUDIT-STACK-REQUIREMENTS.md` + `M-AUDIT-STACK-phases/`

---

## M-AUDIT-CHEMIN — Audit chemin critique fonctionnel (Shipped 2026-05-05)

**Stage 1 du pivot stratégique radical post-v2.7.**

**Plans:** 1 plan (01-01), 13 tasks atomiques, 14 commits
**Output:** `.planning/CRITICAL-PATH-AUDIT.md` (1165 lignes consolidant 12 sections + synthèse)
**Score:** 7✓ / 3⚠ / 0✗ / 1 hors-scope sur 11 étapes audit

**Key findings:**
- ✓ Setup admin / Transition draft→live / Présence+quorum / Vote résolution / Clôture / PV PDF / Hash chain audit
- ⚠ Import CSV/XLSX, Création séance/agenda/motion (motion.kind absent), Procuration (cap incohérence latente)
- ✗ Élection multi-candidats : feature non implémentée → reclassée hors-scope post-décision user

**Stage 3 verdict provisoire** : Voie A confirmée par Stage 2.

**Archived:** `.planning/milestones/M-AUDIT-CHEMIN-MILESTONE-AUDIT.md` + `M-AUDIT-CHEMIN-REQUIREMENTS.md` + `M-AUDIT-CHEMIN-phases/`

---

## Référence pré-pivot (archive)

Pour le contenu détaillé des 28 milestones pré-pivot v1.0 → v2.7, voir `.planning/archive-pre-pivot-2026-05-05/MILESTONES.md`.
