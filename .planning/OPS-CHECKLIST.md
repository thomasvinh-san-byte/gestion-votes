# OPS-CHECKLIST — Actions dev-machine post-v2.5

**Created:** 2026-05-04 — consolidates all "deferred to dev-machine" gates accumulated across v2.3 / v2.4 / v2.5 ship cycles. Run from a normal git clone (no proxy), in order. Each section is independent and can be done in any order, but they're listed roughly by criticality.

> ⚠ **Sandbox limitations summary:** the AI sandbox cannot push/delete on the GitHub remote (HTTP 403 proxy), download Chromium for Playwright (CDN blocked), open a browser for screenshots, or render PDF for visual inspection. Anything below requires hands on a real workstation.

---

## 1. Apply 3 DB migrations on prod (CRITICAL)

Without these, half the new code is silently no-op (capture try/catch swallows DB errors).

```bash
psql "$DB_DSN" -f database/migrations/20260504_error_events.sql
psql "$DB_DSN" -f database/migrations/20260504_next_step_clicks.sql
psql "$DB_DSN" -f database/migrations/20260504_invitation_revoke_pre_hmac.sql
```

All three are **idempotent** (`CREATE TABLE IF NOT EXISTS` / `WHERE revoked_at IS NULL`). Safe to re-run.

**Verify after:**
```sql
SELECT COUNT(*) FROM error_events;        -- should not error
SELECT COUNT(*) FROM next_step_clicks;    -- should not error
SELECT COUNT(*) FROM invitations
  WHERE revoked_at IS NOT NULL;           -- pre-HMAC invitations revoked
```

**Side effect:** all pending/sent invitations (pre-HMAC) are revoked. Operators must re-issue them. Send a heads-up before applying.

---

## 2. Push retroactive tags + delete dead branches (sandbox blocked by proxy)

I created tags **v2.2 / v2.3 / v2.4** locally, plus **v2.5** is already on origin. They need to be pushed:

```bash
# From your machine (not proxy):
git fetch origin
git push origin v2.4 v2.3 v2.2
```

Delete the 5 stale branches:

```bash
git push origin --delete chore/v25-cleanup-dead-v24-artifacts \
                          claude/gsd-ux-review-YG5K0 \
                          feat/v2.2-design-tokens \
                          feat/v2.3-cockpit-operateur \
                          feat/v2.4-cockpit-polish
```

**Verify after:** `git ls-remote --heads origin` shows only `main`. `git ls-remote --tags origin` shows v2.2..v2.5.

---

## 3. Run Playwright suite (Chromium download blocked in sandbox)

```bash
sudo npx playwright install --with-deps chromium     # one-time
cd tests/e2e
npx playwright test --project=chromium
```

**Specs that must be GREEN:**
- `cockpit-button-count.spec.js` — 5 cases including the new "sub-tab Avancé activé" (COCKPIT-V25-01)
- `sse-burst-idempotency.spec.js` — 2 cases (ERR-V24-02 guard)
- `cockpit-keyboard-shortcuts.spec.js` — F-4 modal focus trap réactivé (TEST-V24-01)
- `cockpit-health-bar.spec.js` — non-régression v2.3
- `critical-path-operator.spec.js` — non-régression vote workflow

**If a spec fails:** read the failure, decide if it's a real bug or a flaky test, then either fix the code or update the spec. Don't blindly retry.

**Specs deferred per stop-tests directive (write+run when ready):**
- `tests/Unit/Sse/HeartbeatPayloadTest.php` (HEARTBEAT-V25-03 — never written)
- `tests/e2e/specs/sse-heartbeat.spec.js` (HEARTBEAT-V25-04 — never written)

---

## 4. Visual inspection in browser (screenshots impossible in sandbox)

### 4.1 Cockpit operator
Open `/operator` on a live meeting in Chrome at 1280×720. Verify:
- ≤25 boutons cliquables visibles (idle / voting / proclaiming) — open devtools, count `button:visible, .op-tab:visible, [role="button"]:visible`
- Sub-tab Avancé activé : 4 actions secondaires (Unanimité / Passerelle / Procuration / Suspendre) sont sous une disclosure collapsée par défaut
- Aucun rouge décoratif dans la sidebar — `--color-danger*` confiné aux états critiques
- Barre santé séance affiche les 4 indicateurs (Quorum / SSE / Votants / Résolution)

### 4.2 `meeting.heartbeat` SSE event
Sur la même page :
- Devtools → Network → filter "events.php" → ouvrir le stream
- Devtools → Console : `console.log` patches dans event-stream.js si besoin
- Attendre 12+ secondes, vérifier qu'au moins 1 event `meeting.heartbeat` arrive avec payload `{meeting_id, server_time, status, quorum, operator_count}`
- `quorumStatusBadge` et `quorumStatusDetail` se rafraîchissent visuellement au tick

### 4.3 `/admin/error-stats` page
Connecté en admin :
- Page charge sans erreur
- KPI affichent total + distinct + top code + peak hour
- Timeline SVG render (bar chart)
- Top codes table affiche les events capturés (après que `api_fail()` ait tourné quelques fois)
- Footer affiche storage size + oldest event + commande purge
- Window selector (24h / 3j / 7j / 30j) refresh sans reload page
- Cross-tenant toggle visible (admin-only)

### 4.4 PDF export — 3 PVs longs (≥10 pages)
Générer 3 PVs depuis `/postsession` ou via `MeetingReportsService` :
- Chaque page contient le header `[Titre séance] — JJ/MM/YYYY`
- Footer `Page X sur Y` correct (pagination dompdf)
- 0 contenu coupé en bas de page (em-dash UTF-8 rendu, pas `?`)

### 4.5 CSS smoke regression
17 fichiers CSS migrés tokens v2.4 P4.2/4.3 + 4 supplémentaires v2.5/v2.6 (`vote.css`, `members.css`, `login.css`, `ag-integrity-modal.css`). Ouvrir 5-10 pages clés (login, dashboard, operator, vote, audit, archives, members, settings) et comparer visuellement avec un screenshot pré-token-migration s'il existe. Cherche :
- Borders disparues ou trop épaisses
- Shadows manquants ou trop forts
- Couleurs en `#hex` brut là où il y avait `var(--color-*)` (devtools → Computed)

---

## 5. Cron schedule recommendations

Add to ops cron (or systemd timer, etc.) :

```cron
# Observability tables retention (90j error_events / 180j next_step_clicks)
0 3 * * * cd /var/agvote && php bin/console observability:purge-events >> /var/log/agvote/cron.log 2>&1

# RGPD member purge (per tenant, weekly)
0 4 * * 0 cd /var/agvote && php bin/console rgpd:purge-retention --tenant-id=$TENANT >> /var/log/agvote/cron.log 2>&1

# Email queue processing (every minute)
* * * * * cd /var/agvote && php bin/console email:process-queue >> /var/log/agvote/cron.log 2>&1

# Rate-limit cleanup (every 6h)
0 */6 * * * cd /var/agvote && php bin/console ratelimit:cleanup >> /var/log/agvote/cron.log 2>&1
```

Test each command manually with `--dry-run` (where supported) avant d'activer en cron.

---

## 6. Sanity checks post-deploy

After steps 1-4 are done :

1. **`/api/v1/health`** retourne 200 avec `latency_ms < 50` pour chaque check
2. **`/admin/error-stats`** affiche au moins une rangée après ~10min de trafic
3. **Devtools console** : 0 erreur JS au chargement de `/operator`
4. **Logs PHP** : 0 nouvelle entrée `error_log` brute (cherche `[error]` dans php-fpm log) — toutes les erreurs doivent passer par `Logger::*`
5. **Browser test** : tenter une invitation pré-HMAC déjà-revoquée (par migration #3) → doit retourner `invitation_revoked` proprement

---

## 7. Optional cleanup

Si tu veux dégonfler la cérémonie GSD :

- **Garder** : `MILESTONES.md` (changelog), `STATE.md` (curseur courant), `PROJECT.md` (single-source vision)
- **Archiver / supprimer** : `.planning/v*-MILESTONE-AUDIT.md`, `.planning/v*-REQUIREMENTS.md` (info dupliquée dans MILESTONES.md + git tags)
- **Pattern futur** : 1 PR par phase, merge direct main (pas de milestone branch). Tag v2.X.Y au merge (semver patch). Audit milestone uniquement si > 5 phases.

---

## Summary table

| # | Action | Required | Effort | Blocker if skipped ? |
|---|---|---|---|---|
| 1 | Apply 3 DB migrations | Yes | 2 min | **YES** — observability code is no-op without |
| 2 | Push tags + delete branches | Yes | 1 min | No, but repo is messy |
| 3 | Run Playwright suite | Yes | 5-10 min | Tests written but never run = real risk |
| 4 | Browser visual inspection | Yes | 30-60 min | UX bugs invisibles autrement |
| 5 | Cron schedule | Yes | 10 min | Tables grow indefinitely |
| 6 | Post-deploy sanity | Yes | 15 min | Confirm everything works |
| 7 | Cleanup ceremony | Optional | 1-2h | Quality of life |

**Total minimum effort to close all v2.3/v2.4/v2.5 deferred gates : ~2h on dev-machine.**

---

*Once all 6 required steps are green, this file can be archived to `.planning/milestones/v2.5-OPS-CHECKLIST-DONE.md` (or deleted).*
