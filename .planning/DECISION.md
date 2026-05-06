# Décision direction post-pivot — Stage 3 (M-DECISION)

**Date :** 2026-05-05
**Stage :** 3 (post Stage 1 audit chemin + Stage 2 audit stack)
**Auteur :** orchestrator GSD + décisions user 2026-05-05

## Verdict

**Voie A — Refacto sur place, foundation propre AVANT features.**

La stack technique tient (Stage 2 : 11 keep / 2 replace / 1 remove sur 13 composants). Le chemin critique fonctionnel a 7 étapes ✓ + 3 ⚠ + 1 hors-scope (Stage 1). Pas de rebuild from scratch, pas de rebuild partiel infra. Juste fixer les écarts identifiés et bâtir les 3 features 1.0 par-dessus.

## Justification

### Stage 1 (M-AUDIT-CHEMIN) — Chemin critique

| Étape | Verdict | Action Stage 3 |
|---|---|---|
| 01 Setup admin | ✓ | — |
| 02 Import CSV/XLSX | ⚠ | Fix dans M-INFRA-CLEANUP (cf. AUDIT-CHEMIN-02 + AUDIT-STACK-02 OpenSpout) |
| 03 Création séance/agenda/motion | ⚠ | Fix dans M-INFRA-CLEANUP (motion.kind absent — facile à ajouter sans rebuild) |
| 04 Transition draft→live | ✓ | — |
| 05 Présence + quorum | ✓ | — |
| 06 Vote résolution simple | ✓ | — |
| 07 Élection multi-candidats | ✗ | **Hors-scope du pivot post-décision user** — M-ElectionMotion annulée |
| 08 Procuration | ⚠ | Fix dans M-INFRA-CLEANUP (cap incohérence latente) |
| 09 Clôture séance | ✓ | — |
| 10 PV PDF | ✓ | — |
| 11 Hash chain audit | ✓ | — |

### Stage 2 (M-AUDIT-STACK) — Stack technique

| Composant | Verdict | Effort | Action |
|---|---|---|---|
| dompdf 3.1 | keep | 0 | — |
| phpspreadsheet | **replace → OpenSpout import** | S (1j) | M-INFRA-CLEANUP |
| parsedown | **replace → league/commonmark** | XS (~1h) | M-INFRA-CLEANUP |
| symfony/mailer 8 | keep | 0 | — |
| ext-gd | **remove** | XS (~30min) | M-INFRA-CLEANUP |
| Custom Router/Logger/IdempotencyGuard/Http/SSE | keep | 0 | — |
| Redis | keep | 0 | — |
| **Sessions PHP fichier `/tmp` → Redis** | **migrate** | S (1j) | **M-INFRA-CLEANUP P0** (bloquant UX dogfood) |
| PostgreSQL | keep | 0 | — |
| Docker | keep | 0 | — |

## Roadmap finale post-décision

```
✅ M-AUDIT-CHEMIN  — shipped 2026-05-05 (Stage 1)
✅ M-AUDIT-STACK   — shipped 2026-05-05 (Stage 2)
🚧 M-DECISION      — shipped 2026-05-05 (Stage 3, ce document)
⏳ M-INFRA-CLEANUP — ~2.5-3j (foundation propre)
⏳ M-Signature     — Signature électronique PV eIDAS avancée
⏳ M-VoteDistant   — Vote distant token sans création de compte
⏳ M-Stats         — Stats cross-séance dashboard direction
```

**Décision user 2026-05-05 actée :** infra fix AVANT features. M-INFRA-CLEANUP est la prochaine milestone à ship.

## Scope concret M-INFRA-CLEANUP

**Effort total estimé : ~2.5-3 jours dev**, divisible en 3 phases parallélisables :

### Phase 1 — Sessions Redis (P0, bloquant dogfood)

- **CLEANUP-SESSIONS-01** : Configurer PHP `session.save_handler = redis` + `session.save_path = "tcp://redis:6379?database=1"`
- **CLEANUP-SESSIONS-02** : Migration progressive (cohabitation file/redis pendant transition) ou hard-cutover (acceptable en dogfood)
- **CLEANUP-SESSIONS-03** : Test PHPUnit + Playwright qui prouve que les sessions persistent au redeploy

**Effort : 1j**. **Bloquant pour dogfood** — sans ça, chaque deploy déconnecte tous les utilisateurs.

### Phase 2 — Fixes chemin critique (3 ⚠ Stage 1)

- **CLEANUP-CHEMIN-02** : Import CSV/XLSX edge cases identifiés Stage 1 (dédoublonnage, encodings, validation)
- **CLEANUP-CHEMIN-03** : Création séance/agenda/motion polish (motion.kind absent → ajouter colonne + valeur par défaut `'resolution'`)
- **CLEANUP-CHEMIN-08** : Procuration cap incohérence latente (vérifier code + ajouter tests)

**Effort : 1j cumul** (3 fixes ciblés, ~3-4h chacun max).

### Phase 3 — Quick-wins infra

- **CLEANUP-INFRA-DOC** : Fix doc CLAUDE.md/STACK.md (mention GD pixel email = inexacte)
- **CLEANUP-INFRA-GD** : Retirer `ext-gd` du Dockerfile + composer.json platform reqs
- **CLEANUP-INFRA-PARSEDOWN** : Remplacer `erusev/parsedown` → `league/commonmark` (1-2 sites usage)
- **CLEANUP-INFRA-OPENSPOUT** : Migrer `phpspreadsheet` import → `openspout/openspout` (symétrie avec export streaming existant)

**Effort : 0.5-1j cumul** (XS + XS + XS + S).

## Décisions abandonnées

- **M-ElectionMotion** : élection multi-candidats hors-scope du pivot. Cible (asso/copro/syndicat/conseil municipal) vote majoritairement par résolution Pour/Contre/Abstention. Si demande terrain explicite post-dogfood, re-évaluer en milestone séparée.
- **Voie B (rebuild partiel infra)** : non justifié — Stage 2 conclut que les customs AgVote (Router/Logger/IdempotencyGuard/Http/SSE) sont à garder. Le coût migration vers Slim/Monolog/Symfony Lock dépasserait le bénéfice.
- **Voie C (rebuild from scratch)** : non justifié — Stage 1 montre que 7/11 étapes du chemin critique fonctionnent et que les 3 ⚠ sont fixables sans rebuild.
- **SSE scaling multi-op refacto pub/sub natif** : différé jusqu'à preuve du besoin (>5 op simultanés rare en cas dogfood asso).
- **Mobile app native, AAA accessibility** : hors scope pivot.

## Open questions (à trancher en cours de M-INFRA-CLEANUP ou plus tard)

1. **Migration sessions hard-cutover ou cohabitation ?** — Hard-cutover acceptable en dogfood (pas de prod live). Recommandation : hard-cutover, simpler.
2. **`motion.kind` colonne par défaut ?** — Valeur par défaut `'resolution'` pour compat retrofit. Si élections jamais demandées, on enrichit l'enum à ce moment-là.
3. **`league/commonmark` vs `cebe/markdown` vs autre ?** — `league/commonmark` est la référence PSR. Recommandation : league.
4. **OpenSpout import support .xlsx + .csv ?** — Vérifier doc OpenSpout au démarrage Phase 3.

## Recommandation finale

**Procéder à `/gsd:complete-milestone M-DECISION` puis `/gsd:new-milestone M-INFRA-CLEANUP`** avec les 7 reqs listés ci-dessus (3 sessions + 3 chemin + 4 infra).

Cible dogfood réaliste après M-INFRA-CLEANUP + M-Signature : ~1.5-2 semaines de dev pour avoir une asso pilote qui peut signer un PV électroniquement valide.

---

*Stage 3 du pivot stratégique radical post-v2.7.*
*Décisions user actées : infra fix AVANT features, M-ElectionMotion annulé, Voie A confirmée.*
