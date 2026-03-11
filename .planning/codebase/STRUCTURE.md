# AG-VOTE Project Structure

## Root Directory

```
gestion-votes/
├── app/                    # PHP application code
├── bin/                    # CLI scripts (console, dev.sh, rebuild.sh)
├── config/                 # Application config
├── database/               # Schema, migrations, seeds
├── docker/                 # Docker configs (nginx, supervisord)
├── public/                 # Web root (served by nginx)
├── routes/                 # Route definitions (api.php)
├── tests/                  # PHPUnit tests
├── .claude/                # Claude Code + GSD configuration
├── .planning/              # GSD planning artifacts
├── composer.json           # PHP dependencies
├── Dockerfile              # Multi-stage build
├── docker-compose.yml      # Dev environment
├── Makefile                # Dev workflow commands
├── eslint.config.js        # JS linting config
├── .env.example            # Environment template
└── render.yaml             # Render deployment config
```

## Backend (app/)

```
app/
├── Command/                # Symfony Console commands
├── Controller/             # 38 controllers (one per resource)
│   ├── AbstractController.php  # Base class with helpers
│   ├── AuthController.php      # Authentication endpoints
│   ├── MeetingsController.php  # Meeting CRUD
│   ├── OperatorController.php  # Live meeting operations
│   └── ...
├── Core/
│   ├── Application.php         # App bootstrap
│   ├── Router.php              # URL routing
│   ├── MiddlewarePipeline.php  # Middleware chain
│   ├── Logger.php              # Logging
│   ├── Http/                   # Request/Response helpers
│   ├── Middleware/              # RateLimitGuard, RoleMiddleware
│   ├── Providers/              # DatabaseProvider, EnvProvider, RedisProvider, SecurityProvider
│   ├── Security/               # AuthMiddleware, CsrfMiddleware, PermissionChecker, RateLimiter
│   └── Validation/             # InputValidator, Schemas/
├── Event/                  # Event system (VoteEvents, AppEvent)
│   └── Listener/           # WebSocketListener
├── Repository/             # 30+ repositories (one per entity)
│   ├── AbstractRepository.php
│   ├── Traits/             # Shared repository behaviors
│   └── ...
├── Services/               # 18 business logic services
│   ├── VoteEngine.php          # Core voting logic
│   ├── QuorumEngine.php        # Quorum calculations
│   ├── MailerService.php       # Email sending
│   ├── ExportService.php       # XLSX/CSV generation
│   └── ...
├── Templates/              # PHP templates (emails, reports)
├── View/                   # View helpers
└── WebSocket/              # SSE/WebSocket support
```

## Frontend (public/)

```
public/
├── index.php               # API front controller
├── login.html              # Login page (standalone)
├── index.html              # Landing page
├── *.htmx.html             # 17 app pages (admin, meetings, members, etc.)
├── sw.js                   # Service Worker (PWA)
├── manifest.json           # PWA manifest
├── api/                    # Direct API endpoints (sse.php)
└── assets/
    ├── css/
    │   ├── design-system.css   # Core design tokens + components
    │   ├── app.css             # App shell styles
    │   └── <page>.css          # Per-page styles (20 files)
    ├── js/
    │   ├── shared.js           # Global utilities (Shared namespace)
    │   ├── utils.js            # Helper functions
    │   ├── auth-ui.js          # Auth state + session management
    │   ├── event-stream.js     # SSE client
    │   ├── shell-drawer.js     # Sidebar behavior
    │   ├── mobile-nav.js       # Mobile navigation
    │   ├── pages/              # Per-page modules (29 files)
    │   ├── components/         # Web Components (20 + index.js)
    │   └── vendor/             # Third-party (marked.min.js)
    └── images/                 # Icons, logos, illustrations
```

## Database (database/)

```
database/
├── schema-master.sql       # Complete schema definition
├── migrations/             # Incremental migrations
├── seeds/                  # Demo/test data
└── setup.sh                # DB setup script
```

## Key Entry Points

| Entry Point | Purpose |
|-------------|---------|
| `public/index.php` | API front controller |
| `public/api/sse.php` | Server-Sent Events endpoint |
| `public/login.html` | Authentication UI |
| `public/dashboard.htmx.html` | Main dashboard after login |
| `bin/console` | CLI tool (Symfony Console) |
| `routes/api.php` | Route table definition |
