# Sessions PHP — Migration fichier → Redis DB1

**Date :** 2026-05-06
**Milestone :** M-INFRA-CLEANUP / Phase 1 (CLEANUP-SESSIONS-01..03)
**Statut :** Hard-cutover acté (DECISION.md). Pas de prod live → pas de double-write.

## TL;DR

- **Storage** : Redis DB1, prefix `agvote-sess:`, isolé du cache app (DB0).
- **TTL** : géré par `session.gc_maxlifetime` PHP (1440s par défaut), traduit en `EXPIRE` Redis natif côté phpredis.
- **Persistance** : survit aux `docker compose restart app` (Redis stays up).
- **Casse** : un upgrade Redis avec flush volume = tous les utilisateurs déconnectés. Acceptable hors prod live.

## Configuration

### Variables d'environnement requises

| Variable | Default | Rôle |
|---|---|---|
| `REDIS_HOST` | `redis` | Hostname conteneur Redis |
| `REDIS_PORT` | `6379` | Port Redis |
| `REDIS_PASSWORD` | _(none)_ | Auth — **OBLIGATOIRE en production** |

Le `REDIS_DATABASE` du `.env.example` (=0) reste réservé au cache applicatif (`RedisProvider`). Les sessions vivent sur DB1 indépendamment, choisi en dur dans la chaîne `save_path`.

### Fichiers impliqués

- **`deploy/php.ini`** — déclare `session.save_handler = redis` + `save_path` baseline sans auth (image-bakeable).
- **`deploy/entrypoint.sh`** — injecte un `save_path` authentifié dans `/tmp/php-runtime/zz-runtime.ini` au démarrage si `REDIS_PASSWORD` est set. Dernière déclaration scannée par PHP gagne.
- **`app/Core/Security/SessionHelper.php`** — inchangé. Gère uniquement les cookie params, pas le storage.

## TTL & garbage collection

- phpredis utilise la commande `EXPIRE` Redis sur chaque `SETEX`. Pas de cron PHP `gc` requis.
- Valeur appliquée = `session.gc_maxlifetime` (1440s par défaut, ~24min).
- Pour augmenter : ajouter `session.gc_maxlifetime = 7200` (2h) dans `deploy/php.ini` ou via `runtime` ini.
- Pas de `gc_probability` à régler (non utilisé par handler redis).

## Migration opérationnelle

### Hard-cutover (acté)

1. Push commit avec config Redis sessions.
2. Build image + `docker compose up -d app` (rolling).
3. Tous les utilisateurs avec session active sont déconnectés (sessions `/tmp` n'existent plus dans `/tmp/php-runtime`).
4. Re-login transparent. Pas de page d'erreur, juste un redirect login.

### Pourquoi pas de double-write

Pas de prod live → pas de risque utilisateur perdu. Double-write file+redis demanderait un handler custom (compose), pas justifié pour 0 utilisateur réel.

## Rollback

En cas d'incident lié à Redis sessions, retour fichier en 2 lignes :

```ini
; deploy/php.ini — commenter :
; session.save_handler = redis
; session.save_path = "tcp://redis:6379?database=1&prefix=agvote-sess:"
```

Et désactiver le bloc Redis dans `entrypoint.sh` (commenter le `if [ -n "${REDIS_PASSWORD}" ]`). Rebuild + restart. Tous les utilisateurs déconnectés une fois (re-login).

**Ne pas** rollback en gardant `session.save_handler = redis` mais en supprimant le `save_path` runtime — phpredis utilise alors le baseline non-authentifié et échoue auth, page d'erreur 500 sur chaque request.

## Monitoring

```bash
# Compter les sessions actives sur DB1
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" -n 1 DBSIZE

# Lister les clés sessions (audit ponctuel — éviter en prod, KEYS bloque)
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" -n 1 --scan --pattern 'agvote-sess:*' | head

# Vérifier qu'une session existe pour un session ID donné
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" -n 1 GET "agvote-sess:PHPSESSID_VALUE"

# TTL restant
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" -n 1 TTL "agvote-sess:PHPSESSID_VALUE"
```

Métriques utiles à surveiller :
- **DBSIZE DB1** : doit croître en heures de pointe, retomber après TTL.
- **Memory used** : `INFO memory` — si > 100MB pour DB1 seulement, réduire `gc_maxlifetime`.
- **Evictions** : `INFO stats` `evicted_keys` — si > 0 sur DB1, augmenter `maxmemory` Redis.

## Failure modes

| Cause | Symptôme | Mitigation |
|---|---|---|
| Redis down | HTTP 500 au login | Redis = dépendance dure post-pivot. Healthcheck Redis dans compose. |
| `REDIS_PASSWORD` malformé URL (`?`/`&`/`=`) | Auth fail Redis silencieuse → 500 | Encoder via `printf '%s' \| jq -sRr @uri` si nécessaire. À ce jour `${REDIS_PASSWORD:-agvote-redis-dev}` est safe. |
| Conflit DB1 (autre app) | Sessions et data tierce mélangées | Dédier l'instance Redis à AgVote (déjà le cas dans docker-compose). |
| Corruption Redis DB1 | Sessions invalides → erreurs JSON parse | `FLUSHDB 1` + redéploiement → tous logged out, état propre. |

## Test de non-régression (CI + dev)

- **Unit** : `tests/Unit/SessionRedisConfigTest.php` valide la configuration au niveau ini/script (statique, sans connexion Redis).
- **E2E** : `tests/e2e/specs/session-persistence.spec.js` login → `docker compose restart app` → vérifie session toujours active. Marqué `@slow`.

## Références

- DECISION.md — Sessions Redis P0 (Stage 3).
- AUDIT-STACK-05 — phpredis already loaded, no extension change needed.
- AUDIT-STACK-11 — Redis confirmed `keep`, basis for treating Redis as hard dependency.
- Phpredis session handler doc : <https://github.com/phpredis/phpredis#php-session-handler>
