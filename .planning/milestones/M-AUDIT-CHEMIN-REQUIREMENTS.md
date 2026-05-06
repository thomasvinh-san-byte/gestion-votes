# Requirements: AgVote — M-AUDIT-CHEMIN (Stage 1 post-pivot)

**Defined:** 2026-05-05
**Core Value:** Le secrétaire de séance fait en 5 clics ce qui prenait 1h en papier — avec une traçabilité légale au moins équivalente au procès-verbal manuscrit.

**Goal :** Prouver E2E que le flow user complet marche sur stack live, lister exhaustivement les trous. Aucun fix dans cette milestone — juste constat.

## v1 Requirements

### Audit chemin critique

- [ ] **AUDIT-CHEMIN-01** : Setup admin vierge — install Docker fresh, première connexion, création du compte admin, configuration tenant initial. Verdict ✓/⚠/✗/❓ + reproduction steps si ✗.
- [ ] **AUDIT-CHEMIN-02** : Import CSV membres — fixture 50 membres avec attributs variés (poids vote, statut, email). Vérifier import, dédoublonnage, validation. Verdict + détail.
- [ ] **AUDIT-CHEMIN-03** : Création séance + ordre du jour — wizard de création complet, ajout 3+ motions (résolution + élection + question ouverte). Verdict + détail.
- [ ] **AUDIT-CHEMIN-04** : Ouverture séance live — passage status `draft → frozen → live`, accessibilité opérateur, cockpit chargé. Verdict + détail.
- [ ] **AUDIT-CHEMIN-05** : Émargement présence + quorum — marquer 30/50 membres présents (ou via QR/token), vérifier calcul quorum atteint avec pondération. Verdict + détail.
- [ ] **AUDIT-CHEMIN-06** : Vote motion résolution simple (Pour/Contre/Abstention) — ouvrir vote, votants émettent leurs voix, fermer vote, vérifier comptage. Verdict + détail.
- [x] **AUDIT-CHEMIN-07** : Vote motion élection à plusieurs candidats — **POST-AUDIT DECISION 2026-05-05** : feature non implémentée (audit révèle absence motion_value enum élection + table candidates + scrutin majoritaire dans VoteEngine). Sortie du scope du pivot — voir PROJECT.md "Out of Scope". Verdict audit : ✗ documenté + reclassé hors-scope.
- [ ] **AUDIT-CHEMIN-08** : Vote avec procuration active — assignation procuration entre 2 membres, vérifier que le porteur de procuration vote pour les 2 (pondération doublée respectée). Verdict + détail.
- [ ] **AUDIT-CHEMIN-09** : Clôture séance — passage status `live → closed`, lock des motions, irréversibilité. Verdict + détail.
- [ ] **AUDIT-CHEMIN-10** : Génération PV PDF (≥5 pages avec contenu varié) — header + footer + accents UTF-8 + pagination + signature placeholder. Inspection visuelle. Verdict + détail.
- [ ] **AUDIT-CHEMIN-11** : Archive + audit hash chain — passage `closed → validated → archived`, vérifier hash chain `audit_events.this_hash` cohérent (chaque ligne lien à `prev_hash` précédent), tentative de modif post-archive bloquée. Verdict + détail.
- [ ] **AUDIT-CHEMIN-12** : Synthèse audit — produire `.planning/CRITICAL-PATH-AUDIT.md` consolidant les 11 verdicts ci-dessus, classifié par criticité (bloquant pour dogfood / bloquant pour 1.0 / nice-to-have / esthétique). Listing fait pour informer Stage 3 (décision direction).

## v2 Requirements (post Stage 1, pour Stage 2/3)

À définir post-Stage 1 sur base de l'audit livré.

## Out of Scope (cette milestone)

| Feature | Reason |
|---------|--------|
| Fixer les bugs trouvés pendant l'audit | Stage 1 = constat, fix = Stage 3 décision Voie A/B/C |
| Audit stack technique | Milestone séparée Stage 2 |
| Décision Voie A/B/C | Milestone séparée Stage 3 |
| Implémentation features (Signature, VoteDistant, Stats) | Post-Stage 3 décision |
| Polish UI/UX, refacto code, optimisation perf | Hors-scope pivot — décidé en Stage 3 |
| Stress test multi-utilisateurs >2 op simultanés | Hors-scope dogfood (1 secrétaire typique) |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUDIT-CHEMIN-01 | TBD | Pending |
| AUDIT-CHEMIN-02 | TBD | Pending |
| AUDIT-CHEMIN-03 | TBD | Pending |
| AUDIT-CHEMIN-04 | TBD | Pending |
| AUDIT-CHEMIN-05 | TBD | Pending |
| AUDIT-CHEMIN-06 | TBD | Pending |
| AUDIT-CHEMIN-07 | TBD | Pending |
| AUDIT-CHEMIN-08 | TBD | Pending |
| AUDIT-CHEMIN-09 | TBD | Pending |
| AUDIT-CHEMIN-10 | TBD | Pending |
| AUDIT-CHEMIN-11 | TBD | Pending |
| AUDIT-CHEMIN-12 | TBD | Pending |

**Coverage :**
- v1 requirements : 12 total
- Mapped to phases : 0 (à phaser via /gsd:plan-phase)
- Unmapped : 12

---
*Requirements defined : 2026-05-05*
*Stage 1 du pivot stratégique radical post-v2.7.*
