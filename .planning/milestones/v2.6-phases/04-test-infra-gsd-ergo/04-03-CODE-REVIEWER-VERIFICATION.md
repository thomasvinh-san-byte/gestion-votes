# INFRA-V26-05 — Vérification gsd-code-reviewer sur review v2.6 réelle

**Date** : à compléter lors de l'exécution dev-machine
**Verdict attendu** : ✓ Flags `--timeout-min` + `--scope` opérationnels en conditions réelles
**Statut** : *gate runtime — exécution sur poste dev requise (non exécutable par l'executor parallèle, qui n'a pas accès au dispatch `/gsd:code-review`)*

---

## Contexte

Le plan 04-03 (INFRA-V26-05) requiert **une review v2.6 réelle** qui prouve que l'agent
`gsd-code-reviewer` consomme correctement les flags `--scope=js|php|tests|all` et
`--timeout-min=N` en conditions de production (≥30 fichiers, sans timeout, REVIEW.md valide
généré).

La doc des flags est déjà présente dans l'agent (`.claude/agents/gsd-code-reviewer.md` —
livraison v2.4 P3, conservée v2.6 P4) ; cette gate ne fait que valider le run end-to-end.

---

## Procédure d'exécution (dev-machine)

### Préalable

Être sur une branche / commit qui contient des modifications v2.6 substantielles
(idéalement tous les commits Phase 1 + 2 + 3 réunis ≥30 fichiers).

```bash
git diff --name-only main..HEAD | wc -l
# attendu : ≥30 (ajuster diff_base si nécessaire)
```

### Commande recommandée (option A — focalisée, exerce le scope split)

```
/gsd:code-review --scope=php --timeout-min=45
```

### Alternative (option B — multi-scope avec budget étendu)

```
/gsd:code-review --scope=all --timeout-min=90
```

### Observations à capturer

- Commande exacte invoquée
- Scope effectivement reviewé (`grep files_reviewed_list` dans le REVIEW.md généré)
- Nombre de fichiers (`grep files_reviewed:` dans REVIEW.md)
- Durée totale (invocation → écriture REVIEW.md)
- Path du REVIEW.md
- Statut (`status: clean | issues_found`)
- Si chunking déclenché : nombre de chunks + nettoyage `.planning/.review-progress.json`

---

## Template à compléter post-run

### Commande exécutée

```
/gsd:code-review --scope={js|php|tests|all} --timeout-min={N}
```

### Périmètre de la review

| Métrique | Valeur |
|---|---|
| Diff range | `{base}..HEAD` ({N} commits v2.6) |
| Fichiers in-scope (avant filtre) | {N} |
| Fichiers reviewed (après filtre `--scope`) | {N} |
| Taille totale code reviewed | ~{X} KB |
| Chunking déclenché ? | {oui (N chunks) / non} |

### Timing

| Phase | Durée |
|---|---|
| Invocation → début read files | {Xs} |
| Read files | {Xs} |
| Per-file review | {Xs} |
| Write REVIEW.md | {Xs} |
| **Total** | **{Y} min** (budget : {N} min) |

Marge utilisée : {Y/N × 100}%. Pas de timeout.

### Sortie REVIEW.md

- Path : `{phase_dir}/{phase}-REVIEW.md`
- Frontmatter status : `{clean | issues_found}`
- Findings : `critical={N}, warning={N}, info={N}, total={N}`
- `files_reviewed_list` : voir REVIEW.md (ou coller la liste ici)

---

## Validation INFRA-V26-05

| Critère | Verdict |
|---|---|
| `--timeout-min={N}` respecté (pas de timeout) | ✓ Pas de timeout |
| `--scope={X}` respecté (files_reviewed_list aligné) | ✓ Flags `--scope` respectés |
| REVIEW.md bien-formé (frontmatter + body) | ✓ |
| Chunking auto si >50 fichiers ou >500 KB | {N/A si <50 / ✓ si chunking observé + cleanup} |

---

## Critères d'acceptation

1. Aucun timeout (l'agent termine avant `--timeout-min`).
2. `--scope` respecté : `files_reviewed_list` ne contient que des fichiers du périmètre.
3. REVIEW.md produit, frontmatter YAML + body.
4. Si chunking : `.planning/.review-progress.json` nettoyé en fin.

### En cas d'échec

- Timeout : noter le pourcentage du budget atteint — bug agent à reporter (seed v2.7).
- Scope mal respecté (ex : reviews aussi des `.js`) : bug agent — escalader.
- REVIEW.md non produit : escalader.

---

## Liens

- Agent : [`.claude/agents/gsd-code-reviewer.md`](../../../.claude/agents/gsd-code-reviewer.md)
- Patterns scan : [`.planning/intel/EXPLORE-PATTERNS.md`](../../intel/EXPLORE-PATTERNS.md)
- REVIEW.md généré : à compléter (`{path}`)
- ROADMAP : v2.6 Phase 4 INFRA-V26-05

---

*Verified: à compléter — Plan 04-03 (v2.6 Phase 4) — INFRA-V26-05*
*Note executor parallèle : ce procès-verbal est un template prêt à compléter.*
*La gate runtime requiert un dispatch `/gsd:code-review` sur poste dev, non exécutable*
*depuis le worktree parallèle. La validation finale INFRA-V26-05 se fera après ce run.*
