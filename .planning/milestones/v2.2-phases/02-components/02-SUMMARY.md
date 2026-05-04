---
phase: 2
phase_name: Components
milestone: v2.2
verdict: implemented
date: 2026-04-29
pr: 256
---

# Phase 2 SUMMARY — Components

| Req | Verdict | Approche |
|---|---|---|
| DESIGN-C01 | ✓ | `.btn:disabled` : `opacity:0.45 + cursor:not-allowed`, suppression du `filter:grayscale(30%)`. Chaque variant garde sa teinte (primary translucide, danger translucide, etc.). |
| DESIGN-C02 | ✓ (déjà conforme) | Cards utilisent déjà `--radius-lg` et `--shadow-md` via tokens v2.2. Pas de migration nécessaire. |
| DESIGN-C03 | ✓ (déjà conforme) | Modals/drawers utilisent déjà `--shadow-lg`. Coquille unifiée déjà en place. |
| DESIGN-C04 | ✓ | `.form-input:disabled` + `.form-input[readonly]` ajoutés : background sunken, opacity 0.65, cursor not-allowed. |
| DESIGN-C05 | ✓ (déjà conforme) | Toasts utilisent déjà les tokens sémantiques harmonisés v2.2. |

## Commits
- `feat(v2.2 phase 2): components — disabled state preserves variant identity`
