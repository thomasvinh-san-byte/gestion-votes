# Checklist de mise en production — AG-VOTE

## 1. Variables d'environnement (.env)

```bash
# OBLIGATOIRE : passer en mode production
APP_ENV=production
APP_DEBUG=0

# OBLIGATOIRE : activer la securite
APP_AUTH_ENABLED=1
CSRF_ENABLED=1
RATE_LIMIT_ENABLED=1

# OBLIGATOIRE : changer le secret applicatif (min 64 caracteres)
APP_SECRET=<generer avec: openssl rand -hex 64>

# OBLIGATOIRE : credentials base de donnees production
DB_DSN=pgsql:host=<host>;port=5432;dbname=vote_app
DB_USER=<user_prod>
DB_PASS=<password_prod>

# OBLIGATOIRE : tenant de production
DEFAULT_TENANT_ID=<uuid-du-tenant-production>

# RECOMMANDE : CORS restreint aux origines autorisees
CORS_ALLOWED_ORIGINS=https://vote.mondomaine.fr

# RECOMMANDE : SMTP pour les emails
SMTP_HOST=<smtp-server>
SMTP_PORT=587
SMTP_USER=<user>
SMTP_PASS=<pass>
SMTP_FROM=noreply@mondomaine.fr
```

## 2. Serveur web

### Apache
```apache
# Activer HTTPS redirect (decommenter dans public/.htaccess)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Webroot = /public/
DocumentRoot /var/www/ag-vote/public

# Pages d'erreur personnalisees
ErrorDocument 404 /errors/404.html
ErrorDocument 500 /errors/500.html
```

### Nginx
```nginx
server {
    listen 443 ssl http2;
    root /var/www/ag-vote/public;
    index index.html;

    # Bloquer l'acces aux fichiers sensibles
    location ~ /\.(env|git|htaccess) { deny all; }
    location ~ \.(sql|md|json|lock|xml|yml|yaml|ini|log|sh)$ { deny all; }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    error_page 404 /errors/404.html;
    error_page 500 502 503 504 /errors/500.html;
}
```

## 3. Base de donnees

```bash
# Initialisation complete
bash database/setup.sh --reset

# Ou migration uniquement
bash database/setup.sh --migrate
```

- Verifier que l'utilisateur PostgreSQL a les droits minimaux (pas superuser)
- Activer les backups automatiques (pg_dump quotidien)
- Configurer pg_hba.conf en scram-sha-256 (pas md5, pas trust)

## 4. Cles API

Generer les cles API pour chaque utilisateur via l'interface admin :
- `/admin.htmx.html` > onglet Utilisateurs > creer utilisateur > generer cle
- Distribuer les cles de maniere securisee (pas par email non chiffre)

## 5. Securite - Verification

- [ ] `APP_AUTH_ENABLED=1` dans .env
- [ ] `CSRF_ENABLED=1` dans .env
- [ ] `RATE_LIMIT_ENABLED=1` dans .env
- [ ] `APP_DEBUG=0` dans .env
- [ ] `APP_SECRET` change (pas la valeur par defaut)
- [ ] HTTPS actif avec certificat valide
- [ ] Headers securite presents (verifier avec securityheaders.com)
- [ ] Pas d'acces direct a /.env, /app/, /database/ depuis le web
- [ ] Pas de display_errors en production
- [ ] Logs d'erreur ecrits dans un fichier hors webroot

## 6. Performance

- Activer OPcache PHP (`opcache.enable=1`)
- Configurer la retention des logs d'audit (purge automatique > 2 ans)
- Surveiller `/tmp/ag-vote-ratelimit/` (nettoyage automatique integre)

## 7. Monitoring

- Endpoint health check : `GET /api/v1/ping.php` (retourne `{"ok":true}`)
- Endpoint systeme : `GET /api/v1/admin_system_status.php` (requiert role admin)
- Logs PHP : `/tmp/ag-vote/php_errors.log` (ou configurer dans .env)
