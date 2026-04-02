# Phase 55: Coverage Target & Tooling - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Install pcov/xdebug coverage driver, measure baseline coverage, fill gaps to reach 90%+ on app/Services/ and app/Controller/ directories, and configure phpunit.xml to enforce the 90% threshold.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Prefer pcov over xdebug for coverage (faster). Use phpunit.xml coverage enforcement.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- phpunit.xml — already has coverage configuration pointing to app/Core, app/Services, app/Repository, app/WebSocket, app/Templates
- 2962 unit tests already passing
- Dockerfile installs PHP extensions via docker-php-ext-install

### Established Patterns
- phpunit.xml uses <source> with <include> directories
- Coverage report configured for HTML output to coverage-report/
- PHPUnit 10.5 with coverage attributes support

### Integration Points
- composer.json for pcov dependency
- phpunit.xml for coverage configuration
- CI pipeline (phase 57) will read coverage results

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
