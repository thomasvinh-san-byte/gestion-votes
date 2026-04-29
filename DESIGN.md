# DESIGN.md — Système de design AG-Vote v2.2

**Date :** 2026-04-29
**Source de vérité visuelle.** Toute couleur ou échelle utilisée dans l'application doit être tracée à un token déclaré ici.

---

## 1. Positionnement

AgVote est un outil pour assemblées délibératives à valeur juridique. Le design poursuit :

- **Sérieux civique** — bureau d'huissier, pas startup SaaS
- **Calme moderne** — sobriété, espace, micro-typographie soignée
- **Confiance procédurale** — sécurité ressentie sans austérité
- **Restraint** — l'absence d'effets gratuits est elle-même un signal de qualité

**Références :** DSFR, GOV.UK, USWDS, *Le Monde* numérique.
**Anti-références :** Stripe, Linear, Notion (mauvais persona).

---

## 2. Palette — Light mode

### Brand

| Token | OKLCH | Hex |
|---|---|---|
| `--color-primary` | `oklch(0.45 0.180 265)` | `#2c468f` (Bleu République) |
| `--color-primary-hover` | calc 88% noir | derived |
| `--color-primary-active` | calc 76% noir | derived |
| `--color-primary-subtle` | `oklch(0.93 0.025 265)` | `#dde2f1` |
| `--color-primary-text` | `oklch(1 0 0)` | `#ffffff` |

### Sémantiques (harmonisées au brand, pas Material Default)

| Token | OKLCH | Hex |
|---|---|---|
| `--color-success` | `oklch(0.50 0.130 165)` | `#2d8866` (vert sénat) |
| `--color-danger` | `oklch(0.50 0.165 25)` | `#b2402d` (rouge huissier) |
| `--color-warning` | `oklch(0.62 0.130 75)` | `#a17a30` (ocre archive) |
| `--color-info` | `oklch(0.55 0.140 230)` | `#2c75a8` (bleu instruction) |

### Surfaces (neutre tendant vers blanc — pas blanc pur)

| Token | OKLCH | Hex | Usage |
|---|---|---|---|
| `--color-surface-overlay` | `oklch(0.96 0.001 0)` | `#f4f4f4` | Backdrop modal |
| `--color-bg-subtle` | `oklch(0.975 0.001 0)` | `#f8f8f8` | Sidebars, sunken |
| `--color-bg` | `oklch(0.985 0.001 0)` | `#fbfbfb` | **Fond principal** |
| `--color-surface` | `oklch(0.995 0.001 0)` | `#fdfdfd` | Cards, dropdowns |
| `--color-surface-raised` | `oklch(1 0 0)` | `#ffffff` | Modals, popovers (seul vrai blanc) |

### Texte

| Token | OKLCH | Hex |
|---|---|---|
| `--color-text` | `oklch(0.20 0.010 257)` | `#2c303a` |
| `--color-text-secondary` | `oklch(0.30 0.010 257)` | `#454853` |
| `--color-text-muted` | `oklch(0.42 0.010 257)` | `#5c606b` |
| `--color-text-light` | `oklch(0.55 0.015 257)` | `#767a87` |
| `--color-text-disabled` | `oklch(0.70 0.008 257)` | `#a4a7b1` |

### Bordures

| Token | OKLCH | Hex |
|---|---|---|
| `--color-border-subtle` | `oklch(0.92 0.003 257)` | `#e7e8eb` |
| `--color-border` | `oklch(0.85 0.005 257)` | `#d3d5d9` |
| `--color-border-strong` | `oklch(0.70 0.010 257)` | `#a3a6af` |

---

## 3. Palette — Dark mode

Le dark mode N'EST PAS un light inversé. Trois principes :

1. Aucun noir pur (`#000`) — saturation AMOLED + contraste fatiguant
2. Saturation des accents réduite ~25 %
3. Lightness inversée : un primary à 0.45 en light passe à 0.62 en dark

### Surfaces (5 niveaux d'élévation, hue 260 légèrement bleutée)

| Token | OKLCH | Hex |
|---|---|---|
| `--color-surface-overlay` | `oklch(0.13 0.012 260 / 0.96)` | `#14171f` (backdrop le plus profond) |
| `--color-bg-subtle` | `oklch(0.16 0.014 260)` | `#181d27` (sidebar) |
| `--color-bg` | `oklch(0.20 0.015 260)` | `#1f2531` (**fond principal "encre fine"**) |
| `--color-surface` | `oklch(0.24 0.018 260)` | `#262e3d` (cards, dropdowns) |
| `--color-surface-raised` | `oklch(0.28 0.020 260)` | `#2e3749` (modals, popovers) |

### Texte (pas de blanc pur)

| Token | OKLCH | Hex |
|---|---|---|
| `--color-text` | `oklch(0.95 0.005 257)` | `#f0f1f4` ("ivoire") |
| `--color-text-secondary` | `oklch(0.75 0.012 257)` | `#b3b6be` |
| `--color-text-muted` | `oklch(0.65 0.015 257)` | `#979ba6` |
| `--color-text-light` | `oklch(0.55 0.020 257)` | `#76798a` |
| `--color-text-disabled` | `oklch(0.40 0.020 257)` | `#4f5260` |

### Brand + sémantiques (désaturés et liftés)

| Token | OKLCH | Hex |
|---|---|---|
| `--color-primary` | `oklch(0.62 0.140 265)` | `#6e8be5` |
| `--color-success` | `oklch(0.65 0.110 165)` | `#5fb892` |
| `--color-danger` | `oklch(0.65 0.140 25)` | `#d97a64` |
| `--color-warning` | `oklch(0.75 0.110 75)` | `#d8b25c` |
| `--color-info` | `oklch(0.68 0.120 230)` | `#5ea3d4` |

### Bordures dark

| Token | OKLCH | Hex |
|---|---|---|
| `--color-border-subtle` | `oklch(0.30 0.012 260)` | `#353c4a` |
| `--color-border` | `oklch(0.36 0.015 260)` | `#424a5b` |
| `--color-border-strong` | `oklch(0.45 0.020 260)` | `#555e72` |

---

## 4. Tokens de rôle utilisateur (`--role-*`)

Distincts des `--persona-*` historiques (qui colorent les sections sidebar Préparation / Post-séance / etc.). Les `--role-*` colorent la **bande 3 px en haut** + le **badge persona** dans la sidebar pour identifier l'utilisateur connecté.

Tous dans le spectre froid (240°-330°), différenciés par lightness/saturation, **pas par teintes opposées**.

### Light

| Token | OKLCH | Hex | Rôle |
|---|---|---|---|
| `--role-admin` | `oklch(0.42 0.150 305)` | `#5d3d8a` | violet profond — autorité |
| `--role-president` | `oklch(0.45 0.140 240)` | `#3a5283` | bleu-acier — gravité |
| `--role-operator` | `oklch(0.50 0.180 265)` | `#3850a0` | bleu primaire — action (default) |
| `--role-auditor` | `oklch(0.52 0.040 255)` | `#7a818f` | bleu cendré désaturé — impartialité |
| `--role-voter` | `oklch(0.55 0.150 280)` | `#7261b8` | indigo — engagement |
| `--role-public` | `oklch(0.58 0.080 320)` | `#a285a0` | mauve doux — distance |

### Dark (lift L de ~0.10-0.18, hue conservée du light)

| Token | OKLCH | Hex |
|---|---|---|
| `--role-admin` | `oklch(0.60 0.130 305)` | `#957bcc` |
| `--role-president` | `oklch(0.62 0.120 240)` | `#6e88c6` |
| `--role-operator` | `oklch(0.65 0.150 265)` | `#6688d4` |
| `--role-auditor` | `oklch(0.65 0.040 255)` | `#97a0b1` |
| `--role-voter` | `oklch(0.68 0.130 280)` | `#9c8acc` |
| `--role-public` | `oklch(0.70 0.080 320)` | `#c0a3bc` |

---

## 5. Détection thème — priorité

```
1. JS toggle utilisateur explicite     → [data-theme="light"] ou [data-theme="dark"]
                                          (override le plus fort)
2. @media (prefers-color-scheme: dark)  → applique automatiquement le dark
                                          (si aucun [data-theme] n'est posé)
3. Défaut                                → light mode
```

`:where(:root:not([data-theme="light"]):not([data-theme="dark"]))` garantit que la spécificité du media query reste à (0,0,0) — un toggle JS gagne toujours.

---

## 6. Emails — hex en dur (synchronisé)

Les clients email gèrent mal les variables CSS. Les templates `app/Templates/email_*.php` utilisent des hex inlinés. **Source de vérité = ce tableau.** Quand un token light change ici, on copie le hex dans les templates email.

| Token | Hex (light) | Templates concernés |
|---|---|---|
| `--color-bg` | `#fbfbfb` | `email_invitation.php`, `email_report.php` |
| `--color-text` | `#2c303a` | tous |
| `--color-text-muted` | `#5c606b` | tous (métas) |
| `--color-text-disabled` | `#a4a7b1` (`#767a87` utilisé) | métas finales |
| `--color-primary` | `#2c468f` | `email_invitation.php` (CTA) |
| `--color-success` | `#2d8866` | `email_report.php` (CTA) |

**Les emails sont toujours light** (les clients gèrent mal le dark, et un email d'invitation à voter doit être lisible dans tous les contextes).

---

## 7. Échelles

### Espacement (échelle 8 — déjà en place)

`--space-1` (4px) → `--space-24` (96px). Pas de valeurs en dur en CSS.

### Typographie (déjà en place)

`--text-2xs` (11px) → `--text-5xl` (48px). 11 niveaux.

### Border radius (déjà en place)

`--radius-xs` (3px) → `--radius-2xl` (16px) + `--radius-full` (9999px).

### Shadows

Trois niveaux suffisent :
- `--shadow-sm` — `0 1px 2px rgba(15, 23, 42, 0.05)` — bordure subtile
- `--shadow-md` — `0 4px 12px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04)` — cards élevées
- `--shadow-lg` — `0 12px 32px rgba(15, 23, 42, 0.08), 0 2px 4px rgba(15, 23, 42, 0.05)` — modals

Pas de glassmorphism, pas de glow néon. La hiérarchie passe par la surface, pas par l'ombre.

---

## 8. Vérification

`tests/Visual/tokens.html` rend la palette complète en grille (light + dark via toggle). Ouvrir dans le navigateur après chaque modification de `design-system.css` pour validation à l'œil.

---

## 9. Migration progressive

PR `feat/v2.2-design-tokens` (la fondation, ce document) ne change visuellement **rien** dans les layouts existants — les tokens ont été remappés sur des valeurs proches. Les composants suivants (PR 2 v2.2-components) profiteront pleinement de la nouvelle palette quand ils seront migrés.

---

*Last updated: 2026-04-29 — v2.2 design tokens (Bleu République)*
