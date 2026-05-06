# Roadmap: AgVote (post-pivot 2026-05-05)

## Convention de nommage

Post-pivot, les milestones sont **nommés par feature/audit** plutôt que par version semver. Le versioning produit (v1.0, v2.0) reprendra dès que :
- Le chemin critique est prouvé fonctionnel (Stage 1)
- La stack est auditée + simplifiée si nécessaire (Stage 2)
- Une décision direction est prise (Stage 3)
- Au moins 1 feature métier (Signature/VoteDistant/Stats) est livrée
- 1 asso pilote utilise vraiment l'app

## Milestones

- ✅ **M-AUDIT-CHEMIN** — Audit chemin critique fonctionnel (shipped 2026-05-05) — voir `.planning/milestones/M-AUDIT-CHEMIN-MILESTONE-AUDIT.md`
- ✅ **M-AUDIT-STACK** — Audit stack technique (shipped 2026-05-05) — voir `.planning/milestones/M-AUDIT-STACK-MILESTONE-AUDIT.md`
- 🚧 **M-DECISION** — Décision direction Voie A formalisée + scope fixes + roadmap features (Stage 3)
- ⏳ **M-INFRA-CLEANUP** — Sessions Redis + ext-gd remove + Parsedown→CommonMark + OpenSpout import + 3 fixes ⚠ Stage 1 (~2.5-3j, AVANT features per décision user 2026-05-05)
- ⏳ **M-Signature** — Signature électronique PV eIDAS avancée
- ⏳ **M-VoteDistant** — Vote distant token sans compte
- ⏳ **M-Stats** — Stats cross-séance dashboard direction
- ❌ **M-ElectionMotion** — ANNULÉ post-décision user 2026-05-05

## Phases

### 🚧 M-AUDIT-CHEMIN (in progress)

**Goal** : Prouver E2E que le flow user complet marche aujourd'hui sur stack live, et lister exhaustivement les trous.

**Requirements** : voir `.planning/REQUIREMENTS.md`

**Success Criteria** :
1. Document `CRITICAL-PATH-AUDIT.md` livré listant chaque étape du flow E2E (1. setup admin → 2. import membres → 3. créer séance → 4. ordre du jour → 5. ouvrir séance → 6. émargement présence → 7. vote motion résolution → 8. vote motion élection multi-candidats → 9. vote motion avec procuration assignée → 10. clôture séance → 11. génération PV → 12. archive + audit hash chain) avec verdict ✓ / ⚠ / ✗ / ❓
2. Pour chaque ✗ ou ⚠ : description du problème + reproduction steps + impact sur le pivot (bloquant pour dogfood ? pour 1.0 shipped ?)
3. Audit fait sur stack DEV LIVE (Docker up, DB seedée, services actifs) — pas juste lecture statique de code
4. Stress test minimal : 50 membres importés, 3 motions, 2 procurations actives — vérifier que le quorum + pondération calculent correctement
5. Génération réelle d'un PV ≥5 pages avec contenu varié (résolution + élection + procuration), inspection visuelle
6. Audit ne fait AUCUN fix — juste constat. Tickets fixes = livrable Stage 3 décision

**Plans** : 1 plan

Plans:
- [ ] 01-01-PLAN.md — Audit statique 12 étapes (setup → archive) + synthèse Stage 2/3 vers `.planning/CRITICAL-PATH-AUDIT.md`

### ⏳ M-AUDIT-STACK (planning)

**Goal** : Justifier chaque dépendance et composant custom. Décider keep / replace / remove avec coût-bénéfice.

**Plans** : TBD

### ⏳ M-DECISION (planning)

**Goal** : Sur la base des audits Stage 1+2, décider Voie A (refacto sur place) / Voie B (rebuild partiel infra) / Voie C (rebuild from scratch). Document : `DECISION.md` argumenté + ROADMAP.md révisée.

**Plans** : TBD

### ⏳ M-Signature (planning)

**Goal** : Signature électronique du PV (eIDAS avancée). Sans ça, PV pas valeur légale = blocker dogfood.

**Requirements** : TBD post-décision Stage 3

### ⏳ M-VoteDistant (planning)

**Goal** : Vote distant via token sécurisé (mail/SMS, sans création de compte votant).

**Requirements** : TBD post-décision Stage 3

### ⏳ M-Stats (planning)

**Goal** : Dashboard direction cross-séance (suivi adoption, comparatifs).

**Requirements** : TBD post-décision Stage 3

## Progress

| Milestone | Status | When |
|---|---|---|
| M-AUDIT-CHEMIN | 🚧 In progress | started 2026-05-05 |
| M-AUDIT-STACK | ⏳ Planned | post-Stage 1 |
| M-DECISION | ⏳ Planned | post-Stage 2 |
| M-Signature | ⏳ Planned | post-decision |
| M-VoteDistant | ⏳ Planned | post-Signature |
| M-Stats | ⏳ Planned | post-VoteDistant |

---

*Pre-pivot roadmap (v1.0 → v2.7) archivé dans `.planning/archive-pre-pivot-2026-05-05/ROADMAP.md` pour référence technique.*
