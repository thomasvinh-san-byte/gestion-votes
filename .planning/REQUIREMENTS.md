# Requirements: AgVote v2.1 Hardening Sécurité

**Defined:** 2026-04-29
**Core Value:** L'application doit etre fiable en production — defense en profondeur sur authentification, integrite du vote, isolation tenant, perimetre, uploads et headers HTTP. Aucune fuite cross-tenant tolérée.

**Source des findings :** Audit sécurité du 2026-04-29 (offensive analysis sur branche `claude/code-review-security-ux-ui-DU6oj`) + audit antérieur `SECURITY_AUDIT.md` (2026-02-20). Finding F1 (setup hardening) déjà shipped en v2.0 via PR #247.

---

## v2.1 Requirements

### Sprint 0 finition

- [ ] **HARDEN-F02**: TRUSTED_PROXIES env var + helper `ClientIp::get()` qui n'accepte `X-Forwarded-For` / `X-Forwarded-Proto` qu'à partir d'IPs de proxy whitelistées
- [ ] **HARDEN-F03**: Idempotence sur `degraded_tally` (HTTP 409 au 2ᵉ appel) + audit before/after + reason obligatoire (>= 20 chars)
- [ ] **HARDEN-F04**: Audit trail per-member sur `members_bulk` voting_power (1 événement `member_voting_power_changed` par ID modifié, avec before/after/reason)
- [ ] **HARDEN-F05**: Auth-first dans SSE stream (`public/api/v1/events.php`) + filtrage des événements par `tenant_id` du user authentifié

### Vote intégrité & cross-tenant

- [ ] **HARDEN-F06**: Vote token consommé via `UPDATE ... WHERE used_at IS NULL RETURNING ...` atomique (suppression du `findValidByHash` séparé)
- [ ] **HARDEN-F07**: Migration `invitations.token` (clair) → `invitations.token_hash` (HMAC-SHA256), token clair n'apparaît que dans le mail
- [ ] **HARDEN-F08**: Isolation tenant complète : `AND tenant_id = :t` dans tous les WHERE de `BallotRepository`, `MotionRepository`, `ProxyRepository`, `MeetingAttachmentRepository`, `MemberRepository`
- [ ] **HARDEN-F09**: `MeetingWorkflowController::resetDemo` refuse l'exécution en `APP_ENV=production` pour rôle ≠ admin + validation statut meeting (`draft|scheduled`) + confirmation typée (`RESET-<meeting_code>`)
- [ ] **HARDEN-F10**: CSRF token scopé par action (`HMAC(session_secret, METHOD + PATH)`) au lieu d'un token unique par session

### Périmètre & SSRF

- [ ] **HARDEN-F11**: Helper `UrlValidator::isSafeOutbound($url, $allowedHosts)` (refus RFC1918, link-local, loopback, IDN suspects ; `https` only ; whitelist hôtes exacts) appliqué à `MonitoringService::sendWebhook` et `EmailTrackingController::redirect`
- [ ] **HARDEN-F12**: Rate limiting sur `email_pixel`/`email_redirect` (100 req/60s par IP), sur `password_reset_request` (5 req/600s par IP ET par email), réponse à temps constant sur reset
- [ ] **HARDEN-F13**: Lockout progressif par compte (2^n minutes plafonné à 24h) après N échecs login, header `Retry-After` retourné

### Uploads & contenu

- [ ] **HARDEN-F14**: Upload PDF — défense en profondeur (magic bytes `%PDF-` + finfo + extension, stockage hors webroot par tenant, `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`, `basename()` AVANT `preg_replace`)
- [ ] **HARDEN-F15**: `ExportService` préfixe `'` devant tout cell qui commence par `=`, `+`, `-`, `@`, `\t`, `\r` (CSV ET XLSX)
- [ ] **HARDEN-F16**: dompdf hardening (`setIsRemoteEnabled(false)`, `setIsPhpEnabled(false)`, `setChroot()`, toutes variables user via `htmlspecialchars()`)

### Headers, cookies & defense-in-depth

- [ ] **HARDEN-F17**: Migration CSP de `script-src 'self' 'unsafe-inline'` vers `script-src 'self' 'nonce-$nonce'` (mode Report-Only 1 semaine puis enforce, externalisation des handlers inline)
- [ ] **HARDEN-F18**: Cookies session `SameSite=Strict` (à valider pour vote.php cross-site), `Secure=true` forcé, `HttpOnly=true` ; `session_regenerate_id(true)` à login/logout/changement de rôle
- [ ] **HARDEN-F19**: `AuthMiddleware::getAppSecret()` `throw` si `APP_SECRET < 32 chars` en dev ET prod ; `bootstrap.php` refuse de booter si `APP_ENV=production` et `APP_DEBUG=1`

### Tests & monitoring

- [ ] **HARDEN-F20**: Nouveau dossier `tests/Security/` avec 1 test par finding (cross-tenant, CSRF cross-action, rate-limit IP-spoofing, vote double, formula injection) ; testsuite exécutée à chaque PR
- [ ] **HARDEN-F21**: Logging signal sécurité — échecs login (email + ip_via_proxy + ip_real + UA), alerte sur 10 401/403 en 60s, alerte temps réel sur `audit_events.delete`, `motions.manual_tally`, `members.voting_power`
- [ ] **HARDEN-F22**: `SECURITY_AUDIT.md` mis à jour à chaque sprint avec liens PR/commit + ajout d'un `SECURITY.md` à la racine pour responsible disclosure

---

## v2.2+ Requirements (deferred)

### UI/UX

- **UX-NEXT**: Reprendre l'audit UX/UI du 2026-04-29 (34 findings : 3 critiques modales, 8 élevés a11y, 16 moyens responsive/contrastes, 7 faibles polish) — milestone séparé après stabilisation sécurité

---

## Out of Scope

| Feature | Reason |
|---------|--------|
| UI/UX improvements | Reportés en v2.2+ — focus exclusif sécurité ce milestone |
| Refactoring non-sécuritaire | Hors scope, ne pas mélanger nettoyage et hardening |
| Migration framework (Symfony, Laravel) | Refactoring incrémental seulement, pas de big-bang |
| Application-level test failures (21 tests pré-existants) | Tracés séparément en dette de tests, PR dédiée future |
| Pre-existing 21 PHPUnit failures | Tracé en STATE.md blockers, PR dédiée |
| Auth/refacto majeur (OAuth, SSO) | Hors scope sécu — défense de l'existant uniquement |

---

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| HARDEN-F02 | Phase 1 | Pending |
| HARDEN-F03 | Phase 1 | Pending |
| HARDEN-F04 | Phase 1 | Pending |
| HARDEN-F05 | Phase 1 | Pending |
| HARDEN-F06 | Phase 2 | Pending |
| HARDEN-F07 | Phase 2 | Pending |
| HARDEN-F08 | Phase 2 | Pending |
| HARDEN-F09 | Phase 2 | Pending |
| HARDEN-F10 | Phase 2 | Pending |
| HARDEN-F11 | Phase 3 | Pending |
| HARDEN-F12 | Phase 3 | Pending |
| HARDEN-F13 | Phase 3 | Pending |
| HARDEN-F14 | Phase 4 | Pending |
| HARDEN-F15 | Phase 4 | Pending |
| HARDEN-F16 | Phase 4 | Pending |
| HARDEN-F17 | Phase 5 | Pending |
| HARDEN-F18 | Phase 5 | Pending |
| HARDEN-F19 | Phase 5 | Pending |
| HARDEN-F20 | Phase 6 | Pending |
| HARDEN-F21 | Phase 6 | Pending |
| HARDEN-F22 | Phase 6 | Pending |

**Coverage:**
- v2.1 requirements: 21 total
- Mapped to phases: 21
- Unmapped: 0 ✓

---

*Requirements defined: 2026-04-29 from .planning/research/v2.1-securite-requirements-draft.md*
*Last updated: 2026-04-29 — initial definition*
