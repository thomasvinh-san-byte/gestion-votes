# Phase 12: Page-by-Page MVP Sweep - Context

**Gathered:** 2026-04-08
**Status:** Ready for planning
**Mode:** Auto-generated with locked decisions from milestone escalation

<domain>
## Phase Boundary

Sweep des 21 pages .htmx.html. Chaque page doit passer **3 gates** avant d'etre marquee done :
1. **Width gate** (MVP-01) : pages applicatives en pleine largeur, pages de contenu (docs/help) contraintes ~80ch
2. **Design language gate** (MVP-02) : zero hex/oklch literal, tout en var(--*) du design-system.css
3. **Function gate** (MVP-03) : test Playwright qui assert un changement d'etat reel pour CHAQUE bouton/input/lien principal

Plus jamais "shows cards = done".

</domain>

<decisions>
## Implementation Decisions

### Structure en 5 waves (1 wave = checkpoint utilisateur obligatoire)

Apres chaque wave : **STOP, rapport au user, attendre approbation**.

**Wave 1 — Les pires (4 pages)** : settings, operator, hub, dashboard
**Wave 2 — Workflows core (4 pages)** : meetings, members, vote, wizard
**Wave 3 — Admin pages (4 pages)** : audit, archives, users, admin
**Wave 4 — Reporting & validation (4 pages)** : analytics, report, postsession, validate
**Wave 5 — Edge & content (5 pages)** : trust, public, email-templates, docs, help

Chaque wave produit ses 4-5 plans, executes en parallele si pas de file conflict, tests Playwright passants.

### Methodologie par page

Pour chaque page, le plan doit avoir 3 tasks :

**Task 1 — Width audit** : grep le CSS pour `max-width` artificielle. Si pleine-largeur attendue : retirer la limite OU `max-width: 100%`. Si content (docs/help) : `max-width: 80ch` justifie en commentaire CSS.

**Task 2 — Token audit** : `! grep -nE 'oklch\(|#[0-9a-f]{6}|#[0-9a-f]{3}|rgba?\(' public/assets/css/{page}.css`. Si violations : remplacer par var(--*) ou color-mix(in oklch, var(--*), ...).

**Task 3 — Function audit (Playwright)** : creer ou etendre le spec critical-path-{page}.spec.js. Pour CHAQUE bouton/input/lien principal :
- Click/fill le declenche
- Assert un VRAI changement : DOM update, API 200 + payload, OR DB row apres reload
- Pas d'assertion "le bouton est visible" — c'est insuffisant

### Definition de "fonctionnel" stricte

Un element est fonctionnel si :
- Il a un handler attache (preuve via grep ou test execution)
- Le handler appelle un endpoint qui repond 2xx (preuve via test fetch)
- Le resultat est observable (DOM, navigation, OR API response, OR DB persisted)

Trois preuves combinees, pas une seule.

### Pages deja partiellement couvertes

- Wave 1 settings/operator/hub/dashboard : critical-path tests existent (Phase 9) — etendre
- Wave 2 meetings/members/vote/wizard : page-interactions.spec.js (Phase 7) couvre quelques boutons — etendre
- Wave 3-5 : peu ou pas de couverture, creer les specs

</decisions>

<code_context>
## Existing Code Insights

### Test infrastructure (deja en place)
- bin/test-e2e.sh wrap pour container
- playwright.config.js avec baseURL agvote (Phase 9)
- helpers.js avec loginAsX functions
- waitForHtmxSettled.js helper
- Auth setup via cookie injection

### Specs deja existants
- critical-path-{admin,operator,president,votant}.spec.js (Phase 9 — 4 specs GREEN)
- page-interactions.spec.js (Phase 7 — 8 tests sur 7 pages)
- 18+ legacy specs Phase v1.0/v1.1

### Design system
- design-system.css v2.0 (5278 lignes, OKLCH, @layer)
- @layer pages declared (Phase 6)
- Tokens couleurs/spacing/typography tous definis

### Pre-existing audit
- v1.2-PAGES-AUDIT.md liste les specifics par page (orphans, dead settings, broken endpoints)
- Phase 11 a CLOSE les broken endpoints + dead settings — Phase 12 commence sur une base propre

</code_context>

<specifics>
## Specific Ideas

- L'utilisateur a explicitement demande des **arrets entre waves** ("autonomous indique périodiquement où tu te trouves, arrête-toi à des points-clefs")
- Apres chaque wave : pause, rapport, AskUserQuestion pour continuer
- Si une page necessite plus que les 3 tasks standards (refonte importante), splitter en plusieurs plans
- Si une page passe deja les 3 gates : 1 plan minimal pour "verify only", pas de noise

</specifics>

<deferred>
## Deferred Ideas

- Visual regression testing (snapshot comparison) — v1.3
- Multi-browser matrix (firefox/webkit) — v1.3
- A11y deep audit — v1.3 (axe-core baseline already in place)

</deferred>
