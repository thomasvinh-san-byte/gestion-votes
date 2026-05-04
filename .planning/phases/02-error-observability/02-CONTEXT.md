# Phase 2: Error Observability & Resilience — Context

**Gathered:** 2026-05-04
**Status:** Ready for planning
**Milestone:** v2.4 Polish & Robustness

<domain>
## Phase Boundary

Améliorer l'observabilité des erreurs métier et la résilience SSE :
- Remplacer le code générique `business_error` par 3 codes spécifiques (audit + migration callers, baseline ~11 callers grep)
- Prévenir les double-render des empty-states (modal intégrité, hero card live) sur rafale SSE via debounce ≥250ms
- Enrichir `Logger::error()` avec contexte standardisé sur tous les call-sites + dashboard admin pour métrique taux next-step

**Hors scope** : refonte ErrorDictionary complète (sera v2.5+), nouveau système de logging structuré (toujours `error_log()` sous-jacent), monitoring externe (Sentry/etc. différé).

</domain>

<decisions>
## Implementation Decisions

### ERR-V24-01 — Migration `business_error` (D-01..D-04)

- **D-01**: **Audit-first** — avant toute migration, produire `02.1-AUDIT.md` listant les ~11 callers `business_error` (grep `api_fail('business_error'` + `'business_error'` dans `app/` + `public/api/`). Classification 3 buckets sémantiques.
- **D-02**: **3 codes nommés** dans le PLAN.md à partir de l'audit. Hypothèses initiales (à valider) : `quorum_not_met`, `meeting_state_invalid`, `vote_already_closed`. Le PLAN.md lock les noms définitifs après audit.
- **D-03**: **`business_error` reste** dans `ErrorDictionary.php` comme fallback générique. Pas de suppression. Cible : < 5 % d'usage post-migration (baseline grep avant + vérif après).
- **D-04**: **Test gardien** : `tests/Security/ErrorDictionaryMigrationTest.php` (nouveau ou extension UxConventionsTest) qui assert :
  - Les 3 nouveaux codes existent dans le dictionnaire
  - Chaque nouveau code respecte ERR-02 (virgule + verbe d'action) et ERR-03 (0 phrase bannie)
  - Le compte de callers `business_error` dans `app/` est ≤ N (baseline post-migration, à fixer dans le test après migration — typiquement 0-3 callers résiduels légitimes)

### ERR-V24-02 — Guard idempotence SSE empty-state (D-05..D-07)

- **D-05**: **Debounce ≥250ms** sur les handlers SSE de `<ag-integrity-modal>` + dashboard hero card live. Pattern `setTimeout` + `clearTimeout` avec timer privé par instance.
- **D-06**: **Test E2E** : `tests/e2e/specs/sse-burst-idempotency.spec.js` (nouveau) — injection synthétique 5 events SSE en 100ms via `eventSource.dispatchEvent(...)` ou mock SSE. Assertion : 1 seul render visible (compté via DOM mutation observer ou `data-render-count` attribute).
- **D-07**: **Configurabilité** : debounce-ms exposé en attribut `data-sse-debounce-ms` (défaut 250) pour permettre tuning en dev/test. Valeur < 250 acceptée techniquement mais le défaut impose la borne ERR-02.

### ERR-V24-03 — Logger context + page admin metrics (D-08..D-12)

- **D-08**: **Audit `Logger::error()` callers** — produire `02.3-AUDIT.md` listant les call-sites (grep `Logger::error(` + `Logger::critical(` + `Logger::alert(` dans `app/` + `public/api/`). Estimation 30-50 sites.
- **D-09**: **Context array standardisé** : `Logger::error('msg', ['request_id' => ..., 'user_id' => ..., 'tenant_id' => ..., 'error_code' => ..., 'caller' => __METHOD__])`. Helper `Logger::errorContext()` qui auto-remplit `request_id` (depuis `Logger::getRequestId()`) + `user_id` (`api_current_user_id()`) + `tenant_id` (`api_current_tenant_id()`) à partir du contexte session. Caller fournit `error_code` + `caller`.
- **D-10**: **Page admin `/admin/error-stats.htmx.html`** :
  - Route HTMX déclarée dans `app/routes.php` avec middleware `RoleMiddleware: 'admin'`
  - Controller HTML (extends rien — pattern `HtmlView::render()` per CLAUDE.md)
  - Stats agrégées via `audit_events` table : top N codes émis sur 7/30 jours, taux next-step par code (placeholder : nombre de clics tracking sur next-step suggestion / nombre d'émissions). Si pas de tracking next-step disponible (probable), on documente le placeholder dans le PLAN et on livre le "compte d'émissions" + "taux d'utilisation = N/A pending tracking instrumentation".
- **D-11**: **CSV export** sur page admin (1 bouton "Exporter CSV") — nice-to-have si temps, sinon différé.
- **D-12**: **Pas de nouveau service de tracking next-step** dans cette phase (instrumentation côté HTMX events serait un nouveau req v2.5+). Le dashboard affiche "émissions par code" comme métrique de base, et "taux next-step" comme placeholder N/A documenté.

### Granularité des plans (D-13)

- **D-13**: 3 plans atomiques **parallélisables** (zones disjointes) :
  - **02.1** ERR-V24-01 (PHP services) — `app/Services/ErrorDictionary.php` + callers + test gardien
  - **02.2** ERR-V24-02 (JS components) — `<ag-integrity-modal>` + hero card live + spec Playwright
  - **02.3** ERR-V24-03 (PHP logger + admin page) — `app/Core/Logger.php` + callers + nouveau controller admin + template + route

### Mesure & vérification (D-14)

- **D-14**: Tests gardiens permanents :
  - PHPUnit `ErrorDictionaryMigrationTest.php` (3 tests minimum : codes existent + ERR-02/03 conformes + count callers business_error)
  - Playwright `sse-burst-idempotency.spec.js` (≥1 test : 5 events 100ms → 1 render)
  - PHPUnit `LoggerContextTest.php` (1 test minimum : `Logger::error()` accepte context array, conserve baseline)

### Branche & timing (D-15)

- **D-15**: Phase 2 sur branche `feat/v2.4-cockpit-polish` (réutilisée). Phase 2 démarre **en parallèle de gates dev-machine Phase 1** — zones disjointes (Phase 1 = JS/CSS cockpit, Phase 2 = PHP services + JS components hors cockpit).

### Claude's Discretion

- Naming exact des 3 codes (issu de l'audit 02.1, non décidé maintenant)
- Format CSV export admin (si livré ou différé)
- Sparklines vs tableau plat sur page admin (selon temps)
- Stockage debounce-ms : attribut HTML data-* vs constante JS

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### v2.3 baseline héritée

- `app/Services/ErrorDictionary.php` — 206 codes baseline byte-stable (v2.3 ERR-01 enriched 81 messages)
- `tests/Security/UxConventionsTest.php` — pattern garde permanent ERR-02/ERR-03 (top 50 codes virgule + verbe + 0 phrase bannie)
- `app/Core/Logger.php` — méthodes `error/warning/info/debug` avec context optionnel
- `public/assets/js/components/ag-integrity-modal.js` — custom element shipped v2.3 P1
- `public/assets/js/components/ag-empty-state.js` — extension F2 backward compat shipped v2.3
- `app/routes.php` — pattern routes admin avec `RoleMiddleware`

### v2.3 Decisions héritées (à respecter)

- **ERR-02** convention : virgule + verbe d'action en impératif/subjonctif sur next-step
- **ERR-03** convention : 0 phrase bannie (5 regex listées dans UxConventionsTest)
- **TOP_50_CODES** : liste cristallisée dans v2.3 P4.5 SUMMARY — ne pas re-compter

### Audit sources (input pour PLANs)

- `.planning/phases/04-lexique-ux-critique/04.6-AUDIT.md` (v2.3) — 04.6-FOLLOWUP-2 (3 cas business_error documentés) + 04.6-FOLLOWUP-3 (rafale SSE empty-state)
- `.planning/v2.4-BACKLOG-PLAN.md` — découpage thématique
- `.planning/REQUIREMENTS.md` v2.4 — 3 reqs ERR-V24-01..03

### Backend patterns

- `app/Core/Http/JsonResponse.php` — pattern réponse JSON (réutiliser pour API admin error-stats si applicable)
- `app/Core/Middleware/RoleMiddleware.php` — admin role enforcement
- `bin/console` — pattern commande CLI (référence si CLI préféré pour stats — mais D-10 = page admin HTML)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- **`Logger::getRequestId()`** : fournit déjà `request_id` — le helper `Logger::errorContext()` peut l'utiliser
- **`api_current_user_id()` / `api_current_tenant_id()`** : helpers globaux disponibles via `app/api.php`
- **Pattern `HtmlView::render()`** : utilisé pour login/setup/reset — réutilisable pour `/admin/error-stats.htmx.html`
- **`audit_events` table** : déjà utilisée par `audit_log()` — peut servir de source pour stats si les `api_fail()` sont audit-loggés (à vérifier dans audit)
- **Pattern `<ag-integrity-modal>` v2.3 P1** : custom element vanilla JS — extension `_setupSseDebounce()` pertinente
- **Sentinel anti-double-bind** (pattern `window.AG_OPERATOR_KEYBINDINGS = true`) — réutilisable pour le module SSE debounce

### Established Patterns

- **`api_fail($code, $status, $detail = null)`** : pattern d'émission centralisé dans `app/api.php` — point unique pour migration callers
- **Constructor injection nullable** (CLAUDE.md) : nouveau controller admin reçoit deps via constructor avec `?Type = null` pour tests
- **PHPUnit Security suite** (`tests/Security/`) : pattern test gardien CSS/PHP — modèle pour `ErrorDictionaryMigrationTest`
- **`setTimeout` + `clearTimeout`** : pattern debounce vanilla JS — pas de dépendance externe nécessaire

### Integration Points

- **`api_fail()` callers** : 11+ sites grep `'business_error'` à migrer vers nouveaux codes
- **SSE event handlers** dans `<ag-integrity-modal>` + hero card live (à identifier dans `dashboard.htmx.html` ou `pages/dashboard.js`)
- **Routes existantes admin** (`app/routes.php` section admin) : modèle pour ajouter `/admin/error-stats`

### Audit baseline numbers (à confirmer dans PLANs)

- `grep -rE "api_fail\\('business_error'|business_error\"" app/ public/api/` : **11 callers** (audit fin requis)
- `grep -rE "Logger::(error|critical|alert)\\(" app/ public/api/` : à mesurer (estimation 30-50)
- `<ag-integrity-modal>` SSE handlers : ~2-3 (modal + hero card live)

</code_context>

<specifics>
## Specific Ideas

- **Audit-first impératif** sur ERR-V24-01 : ne pas inventer les 3 codes en avance — laisser l'audit révéler les 3 vrais cas. Les hypothèses (`quorum_not_met`, `meeting_state_invalid`, `vote_already_closed`) sont à confirmer.
- **Debounce 250ms** est la borne minimum — l'attribut `data-sse-debounce-ms` permet de pousser à 500ms si besoin terrain.
- **Page admin error-stats** délibérément simple : 1 controller, 1 template, 1 query agrégée. Ne pas over-engineer (sparklines = nice-to-have, CSV = nice-to-have).
- **Métrique "taux next-step utilisation"** placeholder N/A documenté — l'instrumentation tracking côté HTMX (clics sur suggestion) est un req v2.5+ non bloquant pour Phase 2.
- **Parallélisation Phase 1 + Phase 2** : zones disjointes garantissent 0 conflit. Phase 1 sur cockpit JS/CSS, Phase 2 sur ErrorDictionary PHP / SSE components hors cockpit / admin page nouvelle.

</specifics>

<deferred>
## Deferred Ideas

- **Suppression complète de `business_error`** — déféré v2.5+ (peut nécessiter audit terrain plus exhaustif)
- **Instrumentation tracking next-step côté HTMX** (clics sur suggestion vs ignorance) — déféré v2.5+ requiert nouveau req
- **Monitoring externe Sentry/Datadog** — déféré v2.5+ ou v2.6 sécurité/observability dédié
- **State machine `idle | rendering | rendered`** comme alternative au debounce — déféré, debounce simple suffit pour cas actuel
- **Sparklines / charts sur page admin** — différé si temps insuffisant Phase 2
- **CSV export error-stats** — différé si temps insuffisant Phase 2

</deferred>

---

*Phase: 02-error-observability*
*Context gathered: 2026-05-04*
