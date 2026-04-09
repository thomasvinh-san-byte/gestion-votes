# Phase 8: Test Infrastructure Docker - Context

**Gathered:** 2026-04-08
**Status:** Ready for planning
**Mode:** Discussed with user, decisions locked

<domain>
## Phase Boundary

Faire que Playwright tourne reellement dans un environnement reproductible. Aucun changement aux specs eux-memes — uniquement l'infrastructure d'execution. A la fin de cette phase, `bin/test-e2e.sh` doit lancer la suite Playwright dans un container et retourner un rapport pass/fail.

</domain>

<decisions>
## Implementation Decisions (LOCKED — discussed with user)

### Approche generale
- **Option B**: Service `tests` separe dans `docker-compose.yml`, base image officielle Microsoft Playwright
- PAS d'installation de libatk dans l'image app actuelle (Alpine + chromium = enfer musl)
- PAS de Dockerfile.test custom (l'image officielle suffit)

### Image base
- `mcr.microsoft.com/playwright:v1.59.1-jammy`
- Versionnee pour matcher exactement `@playwright/test 1.59.1` qui est dans tests/e2e/package.json
- Contient deja: chromium, firefox, webkit + tous libs systeme (libatk, libcairo, libpango, libxkbcommon, etc.)

### Browsers dans le scope
- **Chromium seulement** pour ce milestone
- Rationale: bouclage = vitesse + couverture suffisante. Critical path en chromium suffit pour validation.
- Multi-browser (firefox/webkit/mobile) reporte a un milestone polish post-bouclage

### Integration docker-compose
- Nouveau service `tests` dans `docker-compose.yml`
- `profiles: [test]` pour ne PAS se lancer auto avec `docker compose up`
- `depends_on: app` (avec condition healthy)
- Partage le network `backend` avec app, db, redis

### Network / baseURL
- Service `tests` rejoint le network `backend`
- baseURL: `http://app:8080` quand executed dans le container (detected via env var `IN_DOCKER=true` ou `CI=true`)
- Fallback `http://localhost:8080` pour execution locale hors container (preserve compat)

### Script d'execution
- `bin/test-e2e.sh` — wrap simple, executable, passe `$@` a playwright
- Usage: `./bin/test-e2e.sh` (full suite), `./bin/test-e2e.sh --grep @smoke`, `./bin/test-e2e.sh specs/login.spec.js`
- Sous le hood: `docker compose --profile test run --rm tests npx playwright test "$@"`

### Reporter
- **line** reporter au terminal (concis, lisible direct)
- **html** reporter ecrit dans `playwright-report/` (volume mount sur host pour qu'on puisse l'ouvrir)
- Configuration via `playwright.config.js` `reporter: [['line'], ['html', { outputFolder: 'playwright-report' }]]`

### Auth state (cookies)
- Regenere par `global-setup.js` a chaque run dans le container (toujours frais)
- PAS de partage host/container pour eviter les fichiers stale

### Claude's Discretion
- Nom exact du service compose (recommande: `tests`)
- Workdir dans le container (recommande: `/work` avec mount du repo)
- Cache des node_modules (recommande: anonymous volume pour eviter de re-installer a chaque run)
- Permissions: faire run le container en non-root pour eviter les fichiers root sur le host

</decisions>

<code_context>
## Existing Code Insights

### Key Files
- `docker-compose.yml` — services app + db + redis, network `backend`
- `tests/e2e/playwright.config.js` — multi-browser config existante (chromium/firefox/webkit/mobile)
- `tests/e2e/package.json` — `@playwright/test: 1.59.1`, `@axe-core/playwright: 4.10.2`
- `tests/e2e/helpers.js` — loginAs* functions, cookie injection pattern
- `tests/e2e/.auth/*.json` — saved cookies (admin, operator, president, voter)
- `tests/e2e/global-setup.js` — auth setup runs before tests
- `tests/e2e/specs/*.spec.js` — 20+ specs incluant page-interactions et operator-e2e (v1.1)

### Existing patterns
- Tests utilisent baseURL configure dans playwright.config.js
- Auth state via JSON files dans `.auth/`
- Cookies injectes par helpers, pas de form login (rate limit)

### Integration points
- `docker-compose.yml` recoit un nouveau service
- `tests/e2e/playwright.config.js` recoit logique conditionnelle pour baseURL
- `bin/test-e2e.sh` est un nouveau fichier
- `playwright-report/` est un nouveau dossier (gitignore)

</code_context>

<specifics>
## Specific Ideas

- L'utilisateur a explicitement dit "stop aux ajouts" — ce milestone ne doit PAS introduire de nouveaux specs ou modifier les existants. Uniquement infrastructure.
- L'utilisateur veut pouvoir lancer les tests EN LOCAL via Docker, pas seulement en CI
- Le script doit etre simple et previsible — pas de magie
- Si la baseline (specs existants) ne passe pas verte apres setup, c'est un blocker pour Phase 9

</specifics>

<deferred>
## Deferred Ideas

- Multi-browser matrice (firefox + webkit + mobile/tablet) — milestone polish post-bouclage
- Tests en CI GitHub Actions — milestone separe (pour l'instant on valide local)
- Visual regression testing — pas dans scope bouclage
- Performance / lighthouse tests — pas dans scope bouclage

</deferred>
