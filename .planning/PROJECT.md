# AgVote

## What This Is

Application web pour assemblées générales d'associations et collectivités. Remplace le papier (registre, tableau, urne) par un workflow numérique : votes en direct avec quorum/pondération/procurations, PV signé électroniquement, participation distante par token sécurisé, traçabilité légale auditable.

**Cible** : secrétaire de séance non-tech d'une asso loi 1901, copropriété (bureau syndical), syndicat étudiant, conseil municipal, comité d'entreprise.

## Core Value

**Le secrétaire de séance fait en 5 clics ce qui prenait 1h en papier — avec une traçabilité légale au moins équivalente au procès-verbal manuscrit.**

Si tout le reste casse, ça doit marcher.

## Status (post-pivot 2026-05-05)

Le projet a 28 milestones d'historique technique (v1.0 → v2.7) shippés mais **jamais déployés en prod**. Décision pivot 2026-05-05 :

1. Le polish UI/UX/dette technique a saturé sans valeur produit livrée
2. Pas d'utilisateur réel → pas de retour terrain → pas de signal pour prioriser
3. La Core Value était défensive ("ne pas crasher") au lieu d'offensive (battre le papier)

**Réorientation radicale** : valider d'abord le chemin critique fonctionnel + auditer la stack technique, puis livrer 3 features métier qui débloquent un dogfood réel (Signature PV, Vote distant token, Stats cross-séance).

L'historique pré-pivot est archivé dans `.planning/archive-pre-pivot-2026-05-05/`.

## Requirements

### Validated (inferred from shipped code, à re-vérifier en Stage 1)

> Le code existe et a passé des tests, mais aucune preuve de fonctionnement E2E récent. **Tous les "Validated" ci-dessous restent provisoires jusqu'à audit chemin critique.**

- ⚠ Vote en direct avec quorum/pondération/procurations — code v2.0 Operateur Live UX
- ⚠ Cockpit opérateur live (SSE heartbeat, présence multi-op, ag-health-bar)
- ⚠ Audit hash chain immutable (registre légal traçable)
- ⚠ Sécurité défensive F02-F22 (CSRF action-scoped, HMAC tokens, RBAC, rate-limit Redis)
- ⚠ Génération PV PDF (dompdf, header répété, accents UTF-8)
- ⚠ Idempotence sur création/import/email + workflow transitions
- ⚠ Observability (Logger structuré, error_events, /admin/error-stats)

### Active

> Trois étapes séquentielles avant de bouger sur les features métier.

- [x] **Stage 1** ✓ — M-AUDIT-CHEMIN shippé 2026-05-05. Output : `.planning/CRITICAL-PATH-AUDIT.md` (1165 lignes). Score 7✓/3⚠/0✗/1 hors-scope sur 11 étapes.
- [x] **Stage 2** ✓ — M-AUDIT-STACK shippé 2026-05-05. Output : `.planning/STACK-AUDIT.md` (722 lignes). Verdict : 11 keep / 2 replace / 1 remove sur 13 composants. Voie A confirmée.
- [x] **Stage 3** ✓ — M-DECISION shippé 2026-05-05. Output : `.planning/DECISION.md`. Voie A actée + roadmap finale + scope concret M-INFRA-CLEANUP.

Une fois Stage 1+2+3 done **(✓ Stages closed 2026-05-05)** :

- [ ] **M-INFRA-CLEANUP** : Foundation propre AVANT features. 3 phases (~2.5-3j) : Sessions Redis P0 + 3 fixes ⚠ chemin (import/motion.kind/procuration) + quick-wins infra (ext-gd remove + Parsedown→CommonMark + OpenSpout import).

Une fois Stage 1+2+3 done :

- [ ] **Feature M-Signature** : Signature électronique du PV (eIDAS avancée — DocuSign API ou Cryptolib auto-hébergé). Sans ça le PV n'a pas de valeur légale = blocker dogfood.
- [ ] **Feature M-VoteDistant** : Vote distant via token sécurisé (mail/SMS, sans création de compte votant). Augmente le nombre d'utilisateurs réels et valide les autres features.
- [ ] **Feature M-Stats** : Dashboard direction cross-séance (suivi adoption, comparatif quorum atteint, motions récurrentes).

### Out of Scope (explicit boundaries)

| Item | Pourquoi exclu |
|---|---|
| **Élection multi-candidats / scrutin majoritaire** | **Découvert lors Stage 1 audit (2026-05-05) : non implémenté dans le code actuel (motion_value enum limité à for/against/abstain/nsp). Décision user post-audit : pas dans le scope du pivot. La cible (asso loi 1901, copro, syndicat étudiant, conseil municipal) vote majoritairement par résolution Pour/Contre/Abstention. Si demande terrain explicite après dogfood, re-évaluer en milestone séparée.** |
| SaaS multi-tenant + billing self-service | Self-hosted Docker suffit pour la cible asso/collectivité ; multi-tenant ajoute complexité opérationnelle sans valeur user immédiate. |
| Mobile app native (iOS/Android) | Web responsive HTMX suffit ; l'opérateur de séance utilise un laptop, les votants distants peuvent voter sur leur smartphone via web. |
| Accessibility WCAG AAA | AA partiel suffisant pour cible asso ; AAA = effort démesuré pour gain marginal. |
| SSE scaling >10 op simultanés | Cas d'usage rare (la majorité des AG ont 1 secrétaire) ; refacto pub/sub natif différé jusqu'à preuve du besoin. |
| Polish CSS / refonte visuelle continue | Le polish v2.7 a démontré qu'on sature sans valeur produit. Pas de nouveau milestone visuel sans signal terrain explicite. |
| Refacto custom Logger / IdempotencyGuard préventif | À évaluer en Stage 2 audit stack, pas en avance. |
| Capacités notariales avancées (eIDAS qualifiée) | Avancée suffit pour pivot ; qualifiée si demande terrain explicite. |

## Context

**Stack actuelle (à auditer Stage 2)** : PHP 8.4-FPM Alpine + Custom Router + PostgreSQL 16 + Redis 7 + HTMX 2.0.6 + Vanilla JS + dompdf + Symfony Mailer + nginx. Codebase ~15-20k LOC. Tests : PHPUnit + Playwright.

**Dette technique connue (post-v2.7)** :
- 6 pre-existing MeetingsControllerTest failures (update/delete, hors scope v2.7)
- 22 spacing exceptions documentées (1px borders, micro-rem)
- 4 transitions hors palette `--duration-*` (animations scrutin)
- 8 hex print exceptions editorial.css
- INFRA-V26-01 runtime gate Playwright re-test pending dev-machine
- Visual regression 5 pages dev-machine deferred

**Fonctionnel non vérifié récemment** :
- Flow E2E complet (login → archive) jamais re-validé en bloc depuis v2.0
- Génération PV ≥10 pages réelle (smoke test PHPUnit OK, visuel jamais validé)
- Stress test SSE multi-op (jamais fait)

## Constraints

- **Tech stack** : PHP/PostgreSQL/Redis/HTMX préservés tant que Stage 2 ne révèle pas de raison de migrer. Pas de pivot stack avant audit.
- **Compatibility** : ne pas casser les APIs existantes pendant l'audit ; les changements code post-décision Stage 3.
- **Délai** : pas de délai externe — pas d'asso pilote signée. Privilégier décisions justes vs vitesse.
- **Langue** : tout texte user-visible en français. Jamais "copropriété" ou "syndic" (élargi à associations/collectivités).
- **Architecture** : Controllers HTML utilisent HtmlView::render(), API étendent AbstractController. DI par constructeur nullable pour tests.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Pivot stratégique 2026-05-05 | 28 milestones de polish sans utilisateur réel = saturation sans valeur. Core Value défensive trop faible. | — Pending validation post-Stage 3 |
| Cible = remplacer papier (pas SaaS) | User non-tech, low ARPU, self-hosted suffit. UX > tech complexity. | — Pending |
| 3 features 1.0 = Signature + Vote distant + Stats | Identifié comme feature gap critique pour dogfood réel. | — Pending Stage 3 |
| Audit avant build | Construire sur fondation non-vérifiée = risque de re-construire ensuite. | — Pending Stage 1+2 |
| Stack possiblement remplaçable | Custom Router/Logger/IdempotencyGuard à évaluer ; pas de décision a priori. | — Pending Stage 2 |

---

*Last updated: 2026-05-05 after pivot stratégique radical (Stage 0 reset planning, Stage 1 audit chemin critique en cours)*
