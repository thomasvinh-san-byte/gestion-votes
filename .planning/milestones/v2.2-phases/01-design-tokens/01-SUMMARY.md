---
phase: 1
phase_name: Design Tokens
milestone: v2.2
verdict: implemented_awaiting_review
date: 2026-04-29
pr: 256
---

# Phase 1 SUMMARY — Design Tokens

La fondation du redesign v2.2. Pose la palette OKLCH harmonisée (light + dark redesigné), les 6 tokens `--role-*`, le mode dark natif via OS, et synchronise les emails sur la source de vérité documentée.

**Aucun layout ne change visuellement** — c'est volontaire. Les tokens remappent les variables existantes vers des valeurs proches. Les phases 2-4 profiteront pleinement de la nouvelle palette quand elles toucheront aux composants et layouts.

## Requirements covered

| Req | Verdict | Approche | Fichier clé |
|---|---|---|---|
| DESIGN-T01 | ✓ | `--color-primary` → `oklch(0.45 0.180 265)` (#2c468f) | `public/assets/css/design-system.css` |
| DESIGN-T02 | ✓ | Sémantiques harmonisées (chroma 0.13-0.18, lightness 0.45-0.62) | idem |
| DESIGN-T03 | ✓ | Surfaces light neutral pur `oklch(0.985 0.001 0)` (#fbfbfb) | idem |
| DESIGN-T04 | ✓ | Dark redesigné (5 niveaux, hue 260, désat 25%, lift L) | idem |
| DESIGN-T05 | ✓ | 6 tokens `--role-*` (admin/président/opérateur/auditeur/votant/public) | idem |
| DESIGN-T06 | ✓ | `@media (prefers-color-scheme: dark)` via `:where()` spec 0 | idem |
| DESIGN-T07 | ✓ | Templates email synchronisés sur DESIGN.md hex | `app/Templates/email_*.php` |
| DESIGN-T08 | ✓ | `tests/Visual/tokens.html` palette + rôles + toggle | `tests/Visual/tokens.html` |

## Verification

- ✓ `php -l` propre (les seuls PHP touchés sont les templates email, syntaxe inchangée)
- ✓ CSS syntax valide
- ⚠ Tests visuels manuels : à ouvrir `tests/Visual/tokens.html` dans un navigateur, basculer Light/Dark/Auto, comparer
- ⚠ Test prod : envoyer une invitation email, vérifier que les couleurs sont harmonisées avec l'app

## Commits (livrés inline via PR #256)

| SHA | Title |
|---|---|
| (à recompter à push) | feat(v2.2): design tokens — Bleu République + dark redesign + role tokens |

## Notes de processus

Cette phase a été implémentée **avant** d'être formalisée dans GSD (REQUIREMENTS, ROADMAP, etc.). La structure GSD a été ajoutée rétroactivement dans la même branche pour capitaliser les conversations design en planning structuré, conformément à la directive "use GSD properly going forward".

À partir de Phase 2 (Components), le flux normal sera respecté : `/gsd:plan-phase 2` → `/gsd:execute-phase 2` → SUMMARY → PR.

## Out of scope (deferred to phases 2-4 ou v2.3+)

- Refresh des composants (boutons disabled, cards radius/shadow, modals coquille unifiée) — Phase 2
- Wiring des tokens `--role-*` sur les pages (bande 3px, badge sidebar) — Phase 3
- Refonte des layouts (cockpit santé, vote typo, PV éditorial) — Phase 4
- Convention lexicale appliquée — Phase 4
- Switch invitation token hashing → HMAC-SHA256 — v2.3+ (lié sécurité)

## Next

Une fois PR #256 mergée :
1. `/gsd:plan-phase 2` pour planifier le refresh des composants
2. `/gsd:execute-phase 2` quand le plan est validé
3. PR séparée `feat/v2.2-components`
