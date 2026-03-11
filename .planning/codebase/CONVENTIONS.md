# AG-VOTE Coding Conventions

## PHP Conventions

- **Strict types**: `declare(strict_types=1)` in every file
- **PSR-4 autoloading**: Namespace `AgVote\` maps to `app/`
- **Final classes**: Controllers and services declared `final`
- **Named arguments**: Used for clarity in complex method calls
- **Match expressions**: Preferred over switch statements
- **Enums**: PHP 8.1+ enums for fixed value sets (e.g., `Permissions`)
- **Type declarations**: Full type hints on parameters, return types, and properties

### Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `MeetingsController`, `VoteEngine` |
| Methods | camelCase | `fetchMeetings()`, `castBallot()` |
| Variables | camelCase | `$meetingId`, `$tenantId` |
| Constants | UPPER_SNAKE | `MAX_RETRIES` |
| DB columns | snake_case | `tenant_id`, `created_at` |
| Files | PascalCase.php | `MeetingsController.php` |

### Patterns

- **Repository pattern**: All DB access through `*Repository` classes extending `AbstractRepository`
- **Service layer**: Business logic in `*Service` / `*Engine` classes
- **Controller methods**: Thin — validate input, call service, return JSON
- **API helpers**: Global functions `api_json()`, `api_fail()`, `api_query()`, `api_is_uuid()`
- **Error responses**: `api_fail($code, $httpStatus)` with `ErrorDictionary` lookup

## JavaScript Conventions

- **IIFE pattern**: Each page module wrapped in `(function() { ... })();`
- **No ES modules at runtime**: All scripts loaded via `<script>` tags
- **Global namespaces**: `Shared`, `Auth`, `Utils`, `PageComponents`
- **var keyword**: Used throughout (not const/let) for browser compatibility
- **No semicolons required**: Inconsistent usage (both present and absent)

### Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Functions | camelCase | `fetchMeetings()`, `renderTable()` |
| Variables | camelCase | `meetingId`, `currentPage` |
| DOM IDs | camelCase | `kpiTotal`, `btnSave`, `statLive` |
| CSS classes | kebab-case | `app-sidebar`, `ob-banner`, `tour-card` |
| Web Components | kebab-case (ag- prefix) | `<ag-kpi>`, `<ag-modal>` |
| Events | kebab-case | `meeting-updated`, `vote-cast` |

### Patterns

- **DOM-centric**: Direct `document.getElementById()` / `querySelector()` calls
- **Event delegation**: Used on table bodies for row clicks
- **localStorage**: Used for UI persistence (sidebar pin, banner dismiss)
- **Error handling**: `try/catch` around fetch calls, toast notifications for errors
- **Shared utilities**: `Shared.emptyState()`, `Shared.formatDate()`, `Shared.debounce()`

## CSS Conventions

- **Custom properties (design tokens)**: `--color-primary`, `--sidebar-rail`, `--radius-lg`
- **BEM-like naming**: Not strict BEM, but component-scoped classes (`app-sidebar`, `ob-banner`, `ob-body`)
- **No preprocessor**: Plain CSS throughout
- **Theme support**: `[data-theme="dark"]` overrides on custom properties
- **Component scoping**: Per-page CSS files keep styles isolated
- **Utility classes**: Minimal — most styles are semantic

## File Organization

- One controller per resource/domain area
- One repository per database entity
- One JS module per HTML page
- One CSS file per page (plus shared `design-system.css` and `app.css`)
- Web Components each in their own file under `components/`

## Comments

- PHP: PHPDoc on classes and complex methods
- JS: Section headers with `// === SECTION ===` and inline comments for non-obvious logic
- CSS: Section comments `/* --- Section --- */`
- French language used in user-facing strings and some comments
