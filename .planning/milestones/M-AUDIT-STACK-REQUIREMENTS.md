# Requirements: AgVote — M-AUDIT-STACK (Stage 2 post-pivot)

**Defined:** 2026-05-05
**Core Value:** Le secrétaire de séance fait en 5 clics ce qui prenait 1h en papier — avec une traçabilité légale au moins équivalente au procès-verbal manuscrit.

**Goal :** Audit statique de chaque dépendance + composant custom de la stack. Pour chaque ligne : keep / replace / remove avec justification coût/bénéfice. Output : `.planning/STACK-AUDIT.md`.

## v1 Requirements

### Audit dépendances Composer

- [ ] **AUDIT-STACK-01** : `dompdf/dompdf ^3.1` (génération PV PDF). Audit poids, alternatives (mPDF / wkhtmltopdf / Typst), risques runtime (mémoire, CSS subset support). Recoupement avec étape 10 audit chemin (✓ static).
- [ ] **AUDIT-STACK-02** : `phpoffice/phpspreadsheet ^1.29` (Excel/XLSX export ExportService). Audit poids (lourd), usage réel (combien d'imports/exports), alternatives (CSV-only suffisant ? OpenSpout léger ?). Recoupement avec étape 02 audit chemin (⚠).
- [ ] **AUDIT-STACK-03** : `erusev/parsedown ^1.8` (Markdown templates email/doc). Audit usage, alternatives (CommonMark, native PHP), poids.
- [ ] **AUDIT-STACK-04** : `symfony/mailer ^8.0` (SMTP MailerService). Audit version (^8 = bleeding edge ?), alternatives (PHPMailer simple), risques.
- [ ] **AUDIT-STACK-05** : Extensions PHP (`gd`, `intl`, `zip`, `pdo_pgsql`, `pgsql`, `mbstring`, `redis`, `Zend OPcache`). Auditer si toutes utilisées, possibilité retirer.

### Audit composants custom AgVote

- [ ] **AUDIT-STACK-06** : `AgVote\Core\Router` (routing custom). Comparer Slim 4 / Symfony Routing / Laravel. Coût migration vs gain (PSR-15 middleware standard, error handling, named routes). Risque : 100+ routes à re-câbler.
- [ ] **AUDIT-STACK-07** : `AgVote\Core\Logger` (Logger custom JSON). Comparer Monolog (PSR-3 standard). Coût migration (47 sites migrés v2.5) vs gain (handlers Stripe/Sentry/Slack out-of-box, processors).
- [ ] **AUDIT-STACK-08** : `AgVote\Core\IdempotencyGuard` (idempotency custom). Comparer Symfony Lock + interceptor. Coût vs gain (locks distribués, TTL natif).
- [ ] **AUDIT-STACK-09** : `AgVote\Core\Http\*` (Request, JsonResponse, ApiResponseException, HttpCache primitives). Évaluer remplacement par PSR-7 (Nyholm/Laminas) + PSR-15 middleware stack. Coût migration vs gain ecosystem.
- [ ] **AUDIT-STACK-10** : `AgVote\SSE\*` (SseAuthGate, EventBroadcaster, HeartbeatPayloadBuilder). Custom sur custom. Évaluer si Mercure/Centrifugo simplifierait, ou si la complexité actuelle est justifiée.

### Audit infra

- [ ] **AUDIT-STACK-11** : Redis (cache + sessions + rate-limit + SSE queue + presence). Évaluer si toutes les usages sont nécessaires, fallbacks fichier (déjà partiel), risque coupling. Possibilité retirer pour single-tenant self-hosted ?
- [ ] **AUDIT-STACK-12** : PostgreSQL extensions + indexes audit. Vérifier que les indexes hot paths v2.7 (covering indexes) sont effectivement créés via migrations. Détecter indexes manquants probables.
- [ ] **AUDIT-STACK-13** : Docker multi-stage Alpine + nginx + supervisord. Evaluer simplification (FrankenPHP qui replace nginx+php-fpm+supervisord ?), coût migration vs gain (1 binary, simpler ops).

### Synthèse

- [ ] **AUDIT-STACK-14** : Synthèse `.planning/STACK-AUDIT.md` consolidant 13 audits + recommandations actionnables Stage 3 (M-DECISION). Décompte keep/replace/remove + estimation effort migration par item replacé. Verdict global : "Voie A (refacto sur place) confirmée" ou "ajustements infra recommandés".

## v2 Requirements (post-Stage 2)

À définir post-M-AUDIT-STACK sur base de l'audit livré.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Implémentation des migrations recommandées | Stage 2 = audit only ; les migrations effectives = Stage 3 décision + milestones séparées |
| Benchmark performance runtime | Stage 2 = audit statique sandbox ; perf runtime = post-Stage 3 si Voie A confirmée |
| Migration langage (PHP → Go / TypeScript) | Décision pré-audit : reste PHP. Re-evaluation seulement si l'audit révèle gap structurel majeur |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUDIT-STACK-01 | TBD | Pending |
| AUDIT-STACK-02 | TBD | Pending |
| AUDIT-STACK-03 | TBD | Pending |
| AUDIT-STACK-04 | TBD | Pending |
| AUDIT-STACK-05 | TBD | Pending |
| AUDIT-STACK-06 | TBD | Pending |
| AUDIT-STACK-07 | TBD | Pending |
| AUDIT-STACK-08 | TBD | Pending |
| AUDIT-STACK-09 | TBD | Pending |
| AUDIT-STACK-10 | TBD | Pending |
| AUDIT-STACK-11 | TBD | Pending |
| AUDIT-STACK-12 | TBD | Pending |
| AUDIT-STACK-13 | TBD | Pending |
| AUDIT-STACK-14 | TBD | Pending |

**Coverage :**
- v1 requirements : 14 total
- Mapped to phases : 0 (à phaser via /gsd:plan-phase)

---
*Requirements defined : 2026-05-05*
*Stage 2 du pivot stratégique radical post-v2.7.*
