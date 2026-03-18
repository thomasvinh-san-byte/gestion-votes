# Feature & UX Pattern Research

**Domain:** General Assembly voting platform — Guided UX & self-explanatory flows (v4.0)
**Researched:** 2026-03-18
**Confidence:** HIGH (verified across multiple current sources)

---

## Context

This is UX research, not feature gap research. v3.0 shipped a fully wired session lifecycle.
v4.0 goal: transform AG-VOTE into a self-explanatory, visually impressive application where no
user ever needs external help to run a complete assembly.

AG-VOTE screens requiring v4.0 UX treatment:
1. **Dashboard** — overview of all sessions, KPIs, "what to do next"
2. **Session creation wizard** — 4-step creation flow
3. **Session hub** — pre-meeting checklist and staging area
4. **Operator console** — live meeting control panel (PC-first)
5. **Voter view** — in-room voting on mobile
6. **Room display** — projected results screen
7. **Post-session stepper** — results → consolidation → PV → archival
8. **Archives / Records** — completed session library

---

## Platform Analysis: What the Competition Gets Right and Wrong

### Loomio — Collaborative Decision-Making

**What it gets right:**
- Structured discussion-to-proposal flow: every thread moves through a defined lifecycle
  (raise → propose → decide → record), so users always know what phase they're in
- Timeline-based visualisation of where a proposal is in its deliberation arc
- "Proposal" cards are visually distinct from discussion — decision context is never lost

**What it gets wrong:**
- Text-heavy to the point of anxiety for first-time administrators
- No "start here" pathway — you land on a cluttered group dashboard with no clear entry point
- Configuration depth hidden in settings menus; new users don't discover key features (voting
  methods, deadline enforcement) until they stumble on them

**AG-VOTE application:**
- Session hub: adopt Loomio's "proposal card" concept — each AG motion has a card showing
  its current state (draft / open / closed / adopted), not just a table row
- Post-session: visualise the stepper lifecycle the same way Loomio shows deliberation arc

---

### Decidim — Participatory Democracy Platform

**What it gets right (2025 direction):**
- Budgeting pipeline component guides users through a voting process step by step with a
  persistent sidebar checklist that shows overall completion
- Active UX research programme (workshops, focus groups) that feeds the design system —
  evidence-based iteration
- Component-based architecture means every section feels consistent

**What it gets wrong:**
- Administration dashboards are dense and assume expert knowledge of participatory democracy
  vocabulary
- Lack of "what's wrong" feedback when a process is misconfigured — errors appear at vote time

**AG-VOTE application:**
- Wizard: adopt Decidim's persistent sidebar checklist during session creation — always show
  overall progress, not just "step 2 of 4"
- Hub checklist: each item must show WHY it is required, not just whether it is checked

---

### OpaVote — Online Vote Management

**What it gets right:**
- Comprehensive voting method selection (ranked-choice, multiple-winner, etc.)
- Clean minimal ballot design — one question at a time, nothing else visible

**What it gets wrong:**
- "Requires some familiarisation" — classified as technically competent but unfriendly
- Method terminology exposed to admins without explanation ("Meek STV" — what?)
- Setup flow is a flat form, not a guided wizard; everything at once
- Low confidence for accessibility and trust signals

**AG-VOTE application:**
- Anti-pattern to avoid: exposing technical meeting-law terminology without inline
  explanation tooltips (e.g., "majorité absolue" should have a "(?) learn more" affordance)
- Voter ballot: adopt OpaVote's "one question, nothing else" principle for the vote.htmx screen

---

### ElectionBuddy — Election Management

**What it gets right (HIGH confidence — Capterra data):**
- Rated "simple and easy-to-understand" by administrators across 92 verified reviews
- Step-by-step setup guides with templates; users don't start from scratch
- Strong mobile optimisation for voters
- "Stylish ballot" — aesthetics matter for trust

**What it gets wrong:**
- Still primarily a polling tool, not an AG orchestrator — lacks operator console concept

**AG-VOTE application:**
- Template approach: provide preset motion templates for common AG resolutions
  (budget approval, rule change, board election) — reduces blank-slate anxiety in wizard step 3
- Mobile ballot: adopt "stylish ballot" principle — the voter screen is a trust artefact,
  it should look authoritative and considered

---

### Slido — Live Audience Interaction

**What it gets right:**
- Entry friction eliminated: participants join via QR code / URL code, no account needed
- Single-purpose screens: the voter sees ONLY the active question, nothing else
- Real-time visual feedback: results animate in as votes arrive, word clouds grow dynamically
- Present mode: full-screen results projected on room display, separate from operator view
- Facilitator / participant screens are completely separate contexts with no feature bleed

**What it gets wrong:**
- No concept of "meeting lifecycle" — just individual polls, no session arc
- Results display can feel "gamey" (excitement-first) which undermines formal AG gravity

**AG-VOTE application:**
- Voter view: adopt Slido's "single question only" principle — hide all navigation, header,
  sidebar when a motion is open; full-screen vote card
- Room display: adopt Slido's Present mode concept — a dedicated results page optimised
  for projection, not for interaction
- Operator console: adopt the strict facilitator/participant context separation — the operator
  should never feel like they're "on the same page" as voters

---

### Mentimeter — Presentation Polling

**What it gets right:**
- Visual diversity in results: bar charts, word clouds, ranking displays — results are made
  readable, not just accurate
- Presentation integration: slides and polls live in one flow
- "Build and go" creation: you can set up a session in minutes without documentation

**What it gets wrong:**
- Too creative/informal for legal AG contexts — word clouds feel fun, not official
- Format diversity actually adds cognitive load for AG use — operators don't need 12 poll types

**AG-VOTE application:**
- Post-session results display: adopt Mentimeter's commitment to making data beautiful —
  vote counts should be presented with bar charts and percentages alongside numbers
- Anti-pattern: never offer "fun" result formats; stick to the official tally format

---

### BoardEffect / Diligent Boards — Board Meeting Portals

**What they get right:**
- Centralised hub: agendas, minutes, documents, and board books all accessible from one place
  before the meeting
- AI-assisted minutes generation (Diligent) — reduces post-meeting admin burden
- Customisable dashboards: administrators see only what is relevant to their role
- Accessibility across all devices; consistent experience on mobile and desktop

**What they get wrong (relative to AG-VOTE goals):**
- Enterprise-weight software — complex onboarding, assumes IT support
- Feature parity marketed as UX quality; deep feature sets ≠ clarity
- Governance vocabulary still unexplained ("board book", "fiduciary duty" — no tooltips)

**AG-VOTE application:**
- Session hub: adopt BoardEffect's "document + agenda + attendance in one pre-meeting hub"
  concept — the hub page is the single source of truth before the session goes live
- Dashboard: adopt the "role-aware" panel concept — operator dashboard ≠ admin dashboard

---

## Guided Workflow Patterns (Gold Standard Sources)

### Pattern 1: The Staged Wizard (Stripe Checkout, Habstash Fintech)

**What it is:**
A linear multi-step form where each screen handles one coherent theme. Progress is visible
at all times. Users cannot see step 5 data until they complete step 3.

**How Stripe does it:**
- One primary action per screen (never two CTA buttons competing for attention)
- Inline validation: errors appear on blur, not on submit
- Progress indicator is persistent but never the focal point (sidebar or top bar)
- "Save and continue later" available for long flows
- Final review screen before irrevocable commit

**How it applies to AG-VOTE:**

| Screen | Pattern |
|--------|---------|
| Session wizard (4 steps) | Horizontal stepper with named steps: "Informations → Membres → Résolutions → Révision" |
| Each wizard step | One form theme per step, max 5 fields visible; conditional fields revealed on toggle |
| Wizard step 4 (review) | Summary card showing all inputs before "Créer la séance" commit |
| Post-session stepper | Same horizontal stepper: "Résultats → Validation → PV → Archivage" |

**Concrete rules:**
- Disable Next if required fields are empty (show why in inline hint, not a modal)
- Always show "Étape X sur Y" as a number alongside the visual stepper
- Back navigation never loses data (autosave on step exit)
- Limit wizard to max 7 steps; current 4-step structure is correct

---

### Pattern 2: Progressive Disclosure (Notion, Intercom)

**What it is:**
Reveal information as users need it. Hide complexity behind "learn more", "advanced options",
and collapsible sections. Show the essential; offer the rest.

**How Notion does it:**
- First-time empty state shows a sample document with ghosted placeholder text
- Onboarding checklist appears in sidebar: 5 items, each one unlocks the next
- Tooltips triggered on first interaction with any unfamiliar control
- Settings categorised as "basic" vs "advanced" — advanced is behind a toggle

**How Intercom does it:**
- First session: "quick win" guided task (launch Messenger) before any feature overview
- Progress ring in sidebar shows percentage complete
- Contextual help appears in-context, not in a separate help panel

**How it applies to AG-VOTE:**

| Screen | Progressive Disclosure Opportunity |
|--------|-----------------------------------|
| Dashboard (first visit) | Onboarding checklist card appears if no sessions exist |
| Wizard step 3 (résolutions) | Show 2 fields by default; "Ajouter des options de vote avancées" reveals weight/quorum fields |
| Operator console | Tabs collapsed to "Présents | Résolutions | Vote en cours" — advanced tabs (Appareils, Audit) revealed on demand |
| Session hub checklist | Each item shows estimated time to complete; blocked items explain what must happen first |

---

### Pattern 3: Status-Aware Dashboards (Linear, Asana)

**What it is:**
The dashboard reads the current state and surfaces the ONE most important next action.
The UI changes based on where you are in the workflow — it is not static.

**How Linear does it:**
- Inbox surfaces only items that require your attention
- Issue cards show status as colour + icon, not just text
- "Start" button appears contextually on unstarted issues, "Done" on in-progress — the action
  offered matches the item's state

**How Asana does it:**
- Dashboard has a "Your work" section that shows only tasks due soon / blocked
- Project overview shows a visual timeline of completion percentage
- Empty states explicitly say "When [X] happens, [Y] will appear here"

**How it applies to AG-VOTE:**

| State | Dashboard Should Show |
|-------|----------------------|
| No sessions exist | "Créer votre première séance" call to action with illustration |
| Session in draft | "Séance en préparation — Compléter les résolutions [→]" |
| Session frozen (waiting) | "Séance prête — Ouvrir la console opérateur [→]" |
| Session live | "Séance en cours — [live indicator] Rejoindre la console [→]" |
| Session closed | "Séance terminée — Générer le PV [→]" |
| Session archived | Card in muted style, no action CTA |

**Operator console status bar:**
- Quorum indicator: green tick / amber warning / red cross (not just a number)
- Motion status: prominent badge "AUCUN VOTE EN COURS" → "VOTE OUVERT: [Motion title]"
- Time elapsed indicator for open votes (creates urgency awareness)

---

### Pattern 4: The "What to Do Next" Empty State (Cloudscape, Notion, Stripe)

**What it is:**
When a container has no data, never show just an empty table. Always explain the state AND
provide a primary action to resolve it.

**Anatomy (Cloudscape design system):**
1. Heading: "Aucune résolution" (state in plain language)
2. Description (optional): "Les résolutions définissent les sujets soumis au vote pendant la séance."
3. Action: Secondary button "Ajouter une résolution"

**Critical rule:** The action must be a secondary button (not primary), because the empty
state itself is not the main goal of the page — it's a helper.

**How it applies to AG-VOTE — every empty state must have this anatomy:**

| Empty state | Heading | Description | Action |
|-------------|---------|-------------|--------|
| No sessions on dashboard | Aucune séance | Créez votre première séance pour gérer vos votes | Nouvelle séance |
| No motions in wizard step 3 | Aucune résolution | Les résolutions définissent ce qui sera soumis au vote | Ajouter une résolution |
| No members in wizard step 2 | Aucun membre | Importez ou ajoutez les membres qui pourront voter | Importer des membres |
| No results in post-session step 1 | Résultats non disponibles | La séance n'a pas encore été clôturée | — (no action; explain only) |
| No sessions in archives | Aucune séance archivée | Les séances terminées et validées apparaissent ici | — (no action) |

---

### Pattern 5: Contextual Inline Help (Stripe, Intercom, PatternFly)

**What it is:**
Explanatory text lives next to the control that needs it, not in a separate help panel.
Users should never leave the page to understand a field.

**Specific patterns:**

- **Field description:** Under the label, in muted text, one sentence. E.g., "Seuil de quorum —
  Le pourcentage minimum de membres présents pour que les votes soient valables."
- **Tooltip (?) icon:** For optional advanced context. Click to reveal a sentence in a popover.
  Never use hover-only for required information (mobile incompatible).
- **Inline warning:** When a value triggers a business rule, show the implication immediately.
  E.g., entering "50" for quorum threshold triggers: "⚠ Une séance avec ce seuil pourrait
  être annulée si moins de la moitié des membres est présente."
- **Disabled state explanation:** When a button is disabled, show WHY. E.g., "Figer la séance
  — disponible après enregistrement des présences (0/42 membres enregistrés)."

**Anti-pattern:** Modal dialogs for explanations. They interrupt flow and are dismissed without
reading. Reserve modals for destructive action confirmation only.

---

## Live Meeting Operator Console UX

### Pattern 6: The Control Room (Real-Time Dashboards — Smashing Magazine 2025)

**Core principle:** Real-time dashboards are decision assistants, not passive displays.
The interface must shorten time-to-decision under pressure.

**Five design rules for the operator console:**

1. **Maximum 5 data points visible at a time** — quorum status, attendance count, active
   motion name, vote count, time elapsed. Everything else is available but not prominent.

2. **Delta indicators over absolute numbers** — showing "+3 votes in last 30s" is more
   actionable than showing "47 votes". Combine both: "47 votes (+3 ▲)".

3. **Micro-animations 200–400ms** — when vote counts change, use a subtle count-up animation.
   Do not flash or blink. Motion creates attention without panic.

4. **Status via colour + symbol, never colour alone** — "QUORUM ATTEINT ✓" in green is better
   than green indicator only (colourblind users exist; WCAG AA requires non-colour distinction).

5. **Connectivity feedback is trust-critical** — the operator must always know if SSE is live.
   Show: "● En direct" (green pulse) / "⚠ Reconnexion..." (amber) / "✕ Hors ligne" (red).
   Auto-retry with exponential backoff; never silently fail.

**Operator console layout for AG-VOTE (PC-first, 1024px+):**
```
┌─────────────────────────────────────────────────────┐
│  [STATUS BAR] Séance: [Name] | Quorum: ✓ | ● En direct │
├─────────────────────────────────────────────────────┤
│  [LEFT PANEL]          │  [MAIN PANEL]              │
│  Présents (32/42)      │  Résolution active:        │
│  Procurations (4)      │  ┌───────────────────────┐ │
│  ────────────          │  │ VOTE OUVERT           │ │
│  Résolutions           │  │ Approbation budget    │ │
│  ● Résolution 1 ✓      │  │ POUR: 18 | CONTRE: 7  │ │
│  ● Résolution 2 ✓      │  │ ABSTENTION: 3         │ │
│  → Résolution 3 ⏵      │  │ EN ATTENTE: 4         │ │
│  ○ Résolution 4        │  └───────────────────────┘ │
│  ○ Résolution 5        │  [Clôturer le vote] [▼]    │
└────────────────────────┴────────────────────────────┘
```

---

## Voter View UX (Mobile-First)

### Pattern 7: Single-Focus Voting Interface

**Research finding:** The most effective mobile voting UIs eliminate all navigation and
contextual information from view. The voter sees only: what is being voted on, their options.

**Touch target requirements (HIGH confidence — MIT Touch Lab + WCAG 2.5.5):**
- Minimum 44×44px per WCAG AA
- Recommended 48×48px for AG context (includes users with reduced dexterity, formal setting)
- Vote option buttons: full-width cards, minimum 72px height
- At least 8px spacing between options to prevent mis-tap

**Voting card anatomy for AG-VOTE voter view:**
```
┌─────────────────────────────────┐
│  Résolution 3 / 8               │  ← progress context only
│  ─────────────────              │
│  Approbation du budget 2026     │  ← motion title, large
│  (30 000 € pour travaux)        │  ← subtitle if needed, muted
│  ─────────────────              │
│  ┌─────────────────────────┐    │
│  │  ✓  POUR                │    │  ← 72px height min
│  └─────────────────────────┘    │
│  ┌─────────────────────────┐    │
│  │  ✗  CONTRE              │    │  ← 72px height min
│  └─────────────────────────┘    │
│  ┌─────────────────────────┐    │
│  │  —  ABSTENTION          │    │  ← 72px height min
│  └─────────────────────────┘    │
│                                 │
│  ○ Je n'ai pas encore voté      │  ← confirmation state
└─────────────────────────────────┘
```

**Instant feedback rules:**
- On tap: button fills to selected state INSTANTLY (< 50ms — no network round-trip)
- Optimistic UI: show selection immediately, submit in background
- On server confirmation: subtle pulse animation + checkmark replacement
- On server error: roll back selection + show inline error "Vote non enregistré — réessayez"
- Never block the voter UI waiting for server confirmation

**Cognitive load rules:**
- When no vote is open: show "En attente d'un vote" — one line, no other content
- When a vote is open: show ONLY the vote card — no header, no nav, no unrelated info
- When a vote closes: show "Vote enregistré ✓" confirmation for 3 seconds, then return to waiting state
- Never show results to voters during the active vote (breaks secret ballot; also: Slido learned this)

---

## Results Display UX

### Pattern 8: Trustworthy Result Presentation

**What makes results feel trustworthy (Centre for Civic Design + Electpoll research):**
- Show absolute numbers AND percentages together — "18 POUR (56%)" not just one or the other
- Show the total votes cast prominently — "28 votes sur 32 membres présents"
- Show which threshold was required — "Majorité absolue (50% +1): ✓ ADOPTÉ"
- Never animate results into question — smooth counter animations are fine, but no fake drama
- Typographic hierarchy: result (ADOPTÉ / REJETÉ) is the largest element, not the vote counts

**Visual hierarchy for result cards:**

```
┌──────────────────────────────────────────────┐
│  Résolution 3                                │
│  Approbation du budget 2026                  │
│  ─────────────────────────────               │
│                                              │
│         ✓ ADOPTÉ                            │  ← H1, green, large
│    Majorité absolue atteinte                │  ← caption, muted
│                                              │
│  POUR      CONTRE    ABSTENTION   N'A PAS   │
│  18 (56%)  7 (22%)   3 (9%)       4 (13%)   │
│  ████████  ████      ██           ███        │  ← bar charts
│                                              │
│  28 votes exprimés · 32 membres présents     │  ← footer context
└──────────────────────────────────────────────┘
```

**Post-session results page specific rules:**
- Each motion result card is collapsible (default: show adopted/rejected headline; expand for full tally)
- Sort order: by agenda order (not by result)
- Export button at page level: "Exporter tous les résultats (CSV / PDF)"
- Invalid/abstained votes shown separately from "did not vote" — these are legally distinct

---

## Quorum Indicator Patterns

### Pattern 9: Live Quorum Visualisation

**Finding:** No off-the-shelf quorum UX pattern exists in the researched platforms — this is
an AG-specific concept. Pattern derived from voting platform analysis + general progress UI.

**Quorum indicator anatomy (operator console + hub):**

Status: one of three states, always shown as colour + icon + label + number:
- **Not reached:** "Quorum non atteint — 24/42 présents (57%, seuil: 60%)" — amber/warning
- **Reached:** "Quorum atteint ✓ — 28/42 présents (67%)" — green/success
- **With margin context:** Show how many more members needed, or how many could leave

**Progress bar design:**
- Show current attendance as filled bar
- Mark the threshold with a vertical tick at the correct position on the bar
- Fill colour: amber until threshold, green after threshold
- Animate: when a member is marked present, the bar fills incrementally

```
Présents: 28/42  ──────────────────────|─────────────
                 [██████████████████   |             ]
                                       ↑ seuil: 60% = 25 membres
                                   ✓ Quorum atteint
```

**Proxy impact display:**
- Show "(+4 procurations)" alongside the direct presence count
- Tooltip: "Les votes par procuration comptent dans le quorum"
- If quorum is only reached WITH proxies, show: "Quorum atteint avec procurations"

---

## Session Creation Wizard UX

### Pattern 10: Named-Step Wizard with Persistent Context

**Research synthesis from: PatternFly, Eleken, Stripe, Decidim:**

**Step structure for AG-VOTE session creation wizard:**

```
[Informations] → [Membres] → [Résolutions] → [Révision]
     ●               ○           ○              ○
  Step 1/4                                   Review
```

**Step 1 — Informations:**
- Fields: Nom, Date, Heure début/fin, Lieu, Type (ordinaire / extraordinaire)
- Progressive: "Ajouter une convocation" toggle reveals email template fields
- Inline hint on date: "La convocation doit être envoyée au moins 15 jours avant la séance"

**Step 2 — Membres:**
- Empty state: "Aucun membre — Importez une liste ou ajoutez des membres un par un"
- Import CSV: drag-and-drop zone, preview table before confirming
- Manual add: simple inline form below table
- Progressive: "Paramètres de vote avancés" toggle reveals voting power fields

**Step 3 — Résolutions:**
- Empty state: "Aucune résolution — Ajoutez les sujets qui seront soumis au vote"
- Template picker: 3 common templates (Approbation de comptes, Élection au conseil, Modification de règlement)
- Each resolution: title + description + vote type (simple/qualified majority)
- Reorder via drag (or up/down arrows for accessibility)

**Step 4 — Révision:**
- Full summary card: every field from steps 1-3 shown with "Modifier" link per section
- Prominent warning if anything critical is missing: "⚠ Aucun membre ajouté — les votes ne
  pourront pas être attribués"
- Commit button: "Créer la séance →" — primary, single, at bottom right

**Navigation rules:**
- Back button always available, never loses data
- Steps 1-3 are individually saveable (autosave on field blur)
- Cannot reach step 4 if step 1 has validation errors
- Steps 2 and 3 are optional (can create with 0 members, 0 resolutions, and add later from hub)

---

## Anti-Features: UX Patterns to Explicitly Avoid

| Anti-Pattern | Why It Fails | AG-VOTE Specific Risk |
|-------------|--------------|----------------------|
| Modal for explanations | Interrupts flow, dismissed without reading | AG terminology (majorité qualifiée etc.) needs inline help |
| Silent loading without skeleton | Operators cannot tell if data is loading or broken | Operator console live during a meeting — any perceived freeze = panic |
| Error message in console only | Users never see it; they think the app is broken | PHP exceptions that surface as blank screens |
| Disabled button with no explanation | User frustration, support tickets | "Figer la séance" disabled but no tooltip saying why |
| Long-running action without progress | User clicks twice, creating duplicates | PV PDF generation (Dompdf can take 2-5 seconds) |
| Optimistic UI without rollback | Voter thinks they voted; server rejected it | Ballot cast endpoint — must show error if server rejects |
| Global navigation visible during vote | Voter gets distracted, navigates away | Full-screen voter view must hide all non-vote chrome |
| Results revealed during open vote | Secret ballot broken; chilling effect | Voter view must never show partial tallies |
| "Are you sure?" modal chains | Death by confirmation dialogs | Reserve for destructive actions only (delete session) |
| Forcing account creation to vote | Adds friction at the worst moment | Voter authentication via token link is correct; maintain it |
| Showing all features at once | Overwhelm, "I don't know where to start" | Dashboard must start simple and reveal complexity progressively |
| Status via colour alone | WCAG AA failure; colourblind users | Every status indicator: colour + icon + label |

---

## Contextual Next-Action Guidance: Screen-by-Screen Specification

### Dashboard (Admin/Operator first view)

**If zero sessions exist:**
```
┌─────────────────────────────────────────────┐
│  [Illustration: empty meeting room]          │
│                                             │
│  Votre première séance                      │
│  Créez une séance pour commencer à gérer    │
│  vos assemblées générales.                  │
│                                             │
│          [Nouvelle séance →]               │
└─────────────────────────────────────────────┘
```

**If sessions exist, each session card shows the NEXT expected action:**
- draft → "Compléter la configuration"
- scheduled → "Enregistrer les présences"
- frozen → "Ouvrir la console opérateur"
- live → "● En cours — Rejoindre"  (pulse animation on dot)
- closed → "Générer le PV"
- validated → "Archiver la séance"
- archived → [no action; card muted]

### Session Hub (Pre-meeting staging)

**Checklist items with blocked-reason display:**
```
✓ Informations de séance          Complètes
✓ Membres (42)                    Importés
⚠ Résolutions (0)                 [Ajouter des résolutions →]
○ Figer la séance                 Disponible après: résolutions ajoutées
```

**Blocked action tooltip (hover/tap on locked item):**
"La séance ne peut pas être figée tant qu'aucune résolution n'est configurée.
 [Ajouter des résolutions →]"

### Operator Console (Live session)

**Before any vote is opened:**
"Aucun vote en cours — Sélectionnez une résolution et ouvrez le vote."
→ Highlight the résolutions tab in the sidebar

**When a vote is closed:**
"Vote clôturé — Résultats disponibles. Ouvrez le prochain vote ou clôturez la séance."
→ Show two action buttons: "Vote suivant" | "Clôturer la séance"

**End of agenda:**
"Toutes les résolutions ont été traitées — Clôturer la séance pour passer au procès-verbal."
→ "Clôturer la séance →" button in prominent position

### Post-Session Stepper

State-aware step labels:
- Step 1 (Résultats): shows ✓ when all motions have outcomes
- Step 2 (Validation): shows ✓ when meeting transitions to `validated`
- Step 3 (PV): shows ✓ when PDF generated
- Step 4 (Archivage): shows ✓ when archived

Cannot advance to step 2 until step 1 is confirmed.
Cannot advance to step 3 until validation signature entered.
Each step shows estimated time: "Environ 2 minutes"

---

## PDF Resolution Attachments UX (v4.0 New Feature)

**Pattern: Progressive document attachment (Notion, Google Drive integration)**

During wizard step 3 (résolutions):
- Each resolution card has a paperclip icon "Joindre un document"
- On click: file picker (PDF only, max 10MB)
- After upload: thumbnail + filename shown on the resolution card
- Inline preview button: opens PDF in a modal slide-over (not new tab)

During session hub:
- Document attachment status shown on each resolution: "Document joint ✓" or "Aucun document"
- Bulk download: "Télécharger tous les documents (ZIP)"

Voter view (when document attached):
- "Consulter le document" link above the vote options
- Opens as a bottom sheet on mobile (slide-up panel) — voter stays in context
- Document is read-only; cannot be downloaded from voter view (legal protection)

---

## Feature Landscape Summary for v4.0

### Table Stakes (Must Have for v4.0)

| Feature | Why Expected | Complexity | Applies To |
|---------|--------------|------------|------------|
| Status-aware session cards on dashboard | Users need to know what to do for each session | LOW | Dashboard |
| Contextual empty states with actions | "What to do next" on every empty container | LOW | All pages |
| Disabled button explanations | Users must understand WHY they can't proceed | LOW | Hub, wizard, operator |
| Progress stepper with completion states | Post-session flow is opaque without it | MEDIUM | Post-session, wizard |
| Live SSE connectivity indicator | Operators need to trust the live data | LOW | Operator console |
| Quorum progress bar with threshold marker | Quorum is the key pre-vote gate | MEDIUM | Hub, operator |
| Mobile full-screen voter view | In-room voting needs zero distraction | MEDIUM | Voter view |
| Instant optimistic feedback on vote cast | < 50ms response creates trust | MEDIUM | Voter view |
| Trustworthy result cards with all fields | Legal context requires complete result info | MEDIUM | Post-session, room display |

### Differentiators (Set AG-VOTE Apart)

| Feature | Value Proposition | Complexity | Applies To |
|---------|-------------------|------------|------------|
| PDF resolution attachments + inline viewer | Voters can read the full resolution before voting | HIGH | Wizard, voter view |
| Motion template library | Reduces blank-slate anxiety in setup | MEDIUM | Wizard step 3 |
| Session hub as pre-meeting control centre | Single pane: docs + attendance + quorum + agenda | MEDIUM | Hub |
| "Briefing view" before meeting goes live | Operator sees agenda + member list in readable format | MEDIUM | Hub → operator handoff |
| Contextual regulation hints | "La loi du 10 juillet 1965 exige..." inline in the wizard | HIGH | Wizard |
| Role-aware dashboard | Operator sees session cards; admin sees stats + user management | MEDIUM | Dashboard |

### Anti-Features (Do Not Build)

| Anti-Feature | Why Avoid | Alternative |
|-------------|-----------|-------------|
| Help modal / tour overlay | Interrupts, dismissed, forgotten | Persistent contextual hints in UI |
| Separate "help" page only | Users never go there | Inline explanation everywhere |
| Fun result formats (word clouds, rankings) | Undermines legal gravity of AG | Official tally cards only |
| Voter results visible during vote | Breaks secret ballot principle | Lock results until vote closed |
| Polling-based SSE status check | Already have push; don't add pull | Maintain SSE with reconnect |
| Step validation via submit-only | Forces users to complete form before seeing errors | Inline validation on blur |

---

## Sources

- [Loomio collaborative decision-making](https://www.loomio.com/)
- [Decidim 2025 roadmap — UX research](https://meta.decidim.org/processes/news/f/1719/posts/366)
- [OpaVote vs ElectionBuddy comparison — Nemovote 2025](https://blog.nemovote.com/online-voting-software-comparison-2025)
- [ElectionBuddy reviews — Capterra 2025](https://www.capterra.com/p/235336/ElectionBuddy/reviews/)
- [Slido live polling guide](https://blog.slido.com/how-to-use-live-polling-in-a-presentation/)
- [PatternFly wizard design guidelines](https://www.patternfly.org/components/wizard/design-guidelines/)
- [Eleken wizard UI pattern explained](https://www.eleken.co/blog-posts/wizard-ui-pattern-explained)
- [Eleken stepper UI examples](https://www.eleken.co/blog-posts/stepper-ui-examples)
- [Cloudscape empty state patterns — AWS](https://cloudscape.design/patterns/general/empty-states/)
- [Progressive disclosure — Nielsen Norman Group](https://www.nngroup.com/articles/progressive-disclosure/)
- [Userpilot progressive onboarding](https://userpilot.com/blog/progressive-onboarding/)
- [Smashing Magazine: UX strategies for real-time dashboards (2025)](https://smashingmagazine.com/2025/09/ux-strategies-real-time-dashboards/)
- [Mobile UX touch targets — OpenReplay](https://blog.openreplay.com/improving-tap-targets-mobile-ux/)
- [Haptics and mobile feedback — Saropa Medium (2025)](https://saropa-contacts.medium.com/2025-guide-to-haptics-enhancing-mobile-ux-with-tactile-feedback-676dd5937774)
- [Centre for Civic Design — voting system design](https://civicdesign.org/topics/roadmap/)
- [vCast assemblée générale digitale guide (2025)](https://www.vcast.vote/assemblee-generale-digitale-guide-2025/)
- [Voxaly digitalisation vote AG 2025](https://www.voxaly.com/blog/assemblee-generale/la-digitalisation-du-vote-des-resolutions-en-assemblee-generale-en-2025/)
- [BoardEffect vs Diligent Boards comparison — board-room.org](https://board-room.org/blog/compare-diligent-boards-and-boardeffect/)
- [Multi-step form best practices — Webstacks](https://www.webstacks.com/blog/multi-step-form)
- [Voting mobile UX case study — Votera / Gideon Oladimeji](https://medium.com/gideon-case-studies/votera-a-voting-app-case-study-2ae65173b9de)

---
*Feature & UX research for: AG-VOTE v4.0 "Clarity & Flow"*
*Researched: 2026-03-18*
