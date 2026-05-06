# Milestones

> **Pivot stratégique 2026-05-05** : Le projet a été reset post-v2.7. L'historique technique des 28 milestones précédents (v1.0 → v2.7) est archivé dans `.planning/archive-pre-pivot-2026-05-05/MILESTONES.md` pour référence.
>
> Post-pivot, les milestones sont nommés par feature/audit (M-AUDIT-CHEMIN, M-Signature, etc.) plutôt que par version semver. Le versioning produit reprendra dès que la chaîne audit → décision → 1ère feature → dogfood asso est complète.

## M-AUDIT-CHEMIN — Audit chemin critique fonctionnel (Shipped 2026-05-05)

**Stage 1 du pivot stratégique radical post-v2.7.**

**Plans:** 1 plan (01-01), 13 tasks atomiques, 14 commits
**Output:** `.planning/CRITICAL-PATH-AUDIT.md` (1165 lignes consolidant 12 sections + synthèse)
**Score:** 7✓ / 3⚠ / 0✗ / 1 hors-scope sur 11 étapes audit
**Boundary:** PASS — `git status` sur `app/ public/ database/ tests/ composer.json composer.lock` retourne vide

**Key findings:**

- ✓ Setup admin / Transition draft→live / Présence+quorum / Vote résolution / Clôture / PV PDF / Hash chain audit
- ⚠ Import CSV/XLSX (à creuser dev-machine), Création séance/agenda/motion (motion.kind absent), Procuration (cap incohérence latente)
- ✗ Élection multi-candidats : feature non implémentée → **reclassée hors-scope post-décision user 2026-05-05**

**Stage 3 verdict provisoire** : Voie A (refacto sur place). Stack tient, pas de rebuild nécessaire. M-ElectionMotion ANNULÉE.

**Archived:** `.planning/milestones/M-AUDIT-CHEMIN-MILESTONE-AUDIT.md` + `.planning/milestones/M-AUDIT-CHEMIN-REQUIREMENTS.md` + `.planning/milestones/M-AUDIT-CHEMIN-phases/`

---

## Référence pré-pivot (archive)

Pour le contenu détaillé des 28 milestones pré-pivot v1.0 → v2.7, voir `.planning/archive-pre-pivot-2026-05-05/MILESTONES.md`.
