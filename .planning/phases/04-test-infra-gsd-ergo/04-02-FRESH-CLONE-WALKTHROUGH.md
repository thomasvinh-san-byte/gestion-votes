# INFRA-V26-03 — Walkthrough fresh-clone chronométré

**Date prévue** : à exécuter sur dev-machine (parallel executor ne peut
pas valider une fresh-clone Docker en sandbox)
**Stack hôte cible** : Linux/macOS, Node ≥ 18, PHP 8.4+, Docker compose
**Verdict cible** : ✓ ≤ 30 min (1800 s) du `git clone` au premier `passed`

> **Note de cadrage** : ce procès-verbal a été préparé par le plan 04-02
> dans un parallel worktree (sandbox sans Docker, sans accès réseau pour
> `npx playwright install`). Le walkthrough lui-même reste à exécuter
> par un dev sur sa machine. Le présent document fournit (1) la
> procédure prête à exécuter, (2) un dry-run de validation du README, et
> (3) un gabarit de timings à compléter par le dev.

## 1. Procédure d'exécution (à suivre tel quel)

```bash
# 0. Préparer un dossier vierge — simulate fresh clone
TS=$(date +%s)
mkdir -p /tmp/fresh-clone-$TS && cd /tmp/fresh-clone-$TS

# 1. Cloner (chronométrer T0)
T0=$(date +%s)
git clone <url-du-repo> agvote
cd agvote
echo "Étape 1 (clone): $(($(date +%s) - T0))s"

# 2. npm install (depuis tests/e2e/, SOT)
T1=$(date +%s)
cd tests/e2e
npm install
echo "Étape 2 (npm install): $(($(date +%s) - T1))s"

# 3. Browsers (chromium minimum)
T2=$(date +%s)
npx playwright install chromium
echo "Étape 3 (playwright install chromium): $(($(date +%s) - T2))s"

# 4. DB setup + stack
T3=$(date +%s)
cd ../..
docker compose up -d
# attendre que /login.html réponde
until curl -sf http://localhost:8080/login.html > /dev/null; do sleep 2; done
echo "Étape 4 (DB + stack): $(($(date +%s) - T3))s"

# 5. (combiné avec étape 4 via docker compose — noter "skip — fusionné")
T4=$(date +%s)
echo "Étape 5 (server): fusionné avec étape 4 — 0s"

# 6. Premier test
T5=$(date +%s)
cd tests/e2e
TEST_BYPASS_RATELIMIT=1 npx playwright test specs/auth.spec.js --project=chromium --reporter=line
echo "Étape 6 (premier test): $(($(date +%s) - T5))s"

TOTAL=$(($(date +%s) - T0))
echo "TOTAL: ${TOTAL}s — cible ≤ 1800s (30 min)"
```

## 2. Dry-run du README (validation parallel-executor)

Vérifications statiques exécutées dans le worktree avant d'envoyer à un
dev pour le run réel :

| Vérif | Commande | Résultat |
|---|---|---|
| Étape 2 cible bon dossier | `grep "cd tests/e2e" tests/e2e/README.md` | OK — §2 ligne 22 (`cd tests/e2e && npm install`) |
| Étape 3 commande exacte | `grep "playwright install chromium" tests/e2e/README.md` | OK — §2 ligne 23 |
| Étape 4 stack docker | `grep "docker compose up" tests/e2e/README.md` | OK — §2 ligne 24-25 |
| Étape 6 commande exacte | `grep "TEST_BYPASS_RATELIMIT=1 npx playwright test specs/auth.spec.js" tests/e2e/README.md` | OK — §2 ligne 26 |
| Identifiants seed disponibles | `grep "operator@ag-vote.local" tests/e2e/README.md` | OK — §4 |
| Garde-fou opérationnel | `bash bin/check-deps.sh; echo $?` | OK — exit 0 |

**Conclusion dry-run** : le README est cohérent et chaque étape de la
checklist §2 est documentée. Aucune commande introuvable, pas de
référence à un binaire absent (`bin/console db:seed --env=test` etc. n'a
pas été utilisé).

## 3. Timings par étape (à compléter par le dev)

| # | Étape | Durée | Référence README | Note |
|---|---|---|---|---|
| 1 | `git clone` | ___ s | §2 step 1 | dépend de la bande passante |
| 2 | `npm install` (tests/e2e) | ___ s | §2 step 2 | ~30-90 s |
| 3 | `npx playwright install chromium` | ___ s | §2 step 3 | borné par CDN Chromium (~3-5 min) |
| 4 | `docker compose up -d` + attente health | ___ s | §2 step 4 | ~30-60 s si images en cache, 5-10 min si build |
| 5 | Serveur dev | 0 s | §2 step 5 | fusionné avec étape 4 (docker) |
| 6 | Premier test (`auth.spec.js --project=chromium`) | ___ s | §2 step 6 | ~10-20 s (3 tests dans le fichier) |

**TOTAL attendu** : ~ 600-1200 s (10-20 min) sur connexion correcte +
images docker cachées.

**TOTAL réel** : ___ s (___ min)

## 4. Sortie test step 6 (à coller par le dev)

```
$ TEST_BYPASS_RATELIMIT=1 npx playwright test specs/auth.spec.js --project=chromium --reporter=line

  Running N tests using 1 worker

  ...

  N passed (Xs)
```

## 5. Trous README détectés et patchés

Pendant la préparation de ce walkthrough (parallel executor v2.0
baseline) :

### Patch §0 — création du README v2.6 complet

**Trou** : le README v1.x présent sur la baseline ne couvrait que ~80
lignes (prérequis succincts, lancement, structure) et omettait :
- Les 5 sections requises par INFRA-V26-03 (sauf install + browsers).
- Le rationale dual-install (sujet d'INFRA-V26-02).

**Fix** : réécriture en 6 sections (§1 Prérequis, §2 First-time setup
≤30 min, §3 Running tests, §4 Auth-setup et rate-limit, §5 Common
pitfalls + §5.5 Écrire un nouveau spec, §6 Debug procedures).

**Commit** : `docs(04-02): expand tests/e2e/README.md with 5 required sections`
(hash inscrit par task 4 du plan).

### Patch §0bis — création de `bin/check-deps.sh`

**Trou** : le garde-fou attendu par INFRA-V26-02 (must_haves truth #1)
n'existait pas sur la baseline, et `@playwright/test` était présent dans
le root `package.json`.

**Fix** :
1. Création de `bin/check-deps.sh` (4 exit codes).
2. Suppression de `@playwright/test` des devDependencies racine.
3. Conversion des scripts `test:e2e` racine en proxy
   `cd tests/e2e && npx playwright test`.
4. Ajout du script `npm run check:deps`.

**Commit** : `feat(04-02): add bin/check-deps.sh dual-install guardrail`
(hash inscrit par task 4 du plan).

> Tout walkthrough lancé après ces deux commits doit retrouver l'état
> attendu par INFRA-V26-02 (garde-fou exit 0) et INFRA-V26-03 (5
> sections couvertes). Si le dev découvre un trou résiduel, il l'ajoute
> ci-dessous.

### Trous résiduels (réservé au dev qui exécute)

```
{À COMPLÉTER : aucun trou détecté / trou X patché à la ligne Y du README}
```

## 6. Conclusion

Le README `tests/e2e/README.md` permet à un nouveau contributeur de
passer du clone au premier test vert en ≤ 30 min, sous réserve que la
machine dev dispose déjà de Node 18+, Docker compose et PHP 8.4
(prérequis §1 du README).

INFRA-V26-03 satisfaite à condition que le walkthrough effectif retourne
un total ≤ 30 min — gabarit chronométrage prêt §3 ci-dessus.

> **Action restante pour clore INFRA-V26-03** : un dev exécute la
> procédure §1, complète les timings §3 + la sortie §4, et ajoute la
> ligne "Verdict réel : ✓ Xmin" en haut de ce document. Si dépassement
> > 30 min, documenter quelle étape a fait basculer (le plus probable :
> téléchargement Chromium > 5 min sur connexion lente — borné par le
> CDN, acceptable).

## Liens

- README : [`tests/e2e/README.md`](../../../tests/e2e/README.md)
- Audit dual-install : [`04-02-DUAL-INSTALL-AUDIT.md`](./04-02-DUAL-INSTALL-AUDIT.md)
- Plan source : [`04-02-PLAN.md`](./04-02-PLAN.md)
- ROADMAP : v2.6 Phase 4 INFRA-V26-03

---
*Walkthrough scaffold: 2026-05-05 — Plan 04-02 (v2.6 Phase 4) — INFRA-V26-03 — TOTAL: à compléter min — Verdict: ≤30 min cible (à confirmer par dev)*
