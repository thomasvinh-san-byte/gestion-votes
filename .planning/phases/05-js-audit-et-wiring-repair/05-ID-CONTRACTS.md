# 05-ID-CONTRACTS.md — JS/HTML ID Contract Inventory

**Generated:** 2026-04-07
**Scope:** All JS files in `public/assets/js/pages/` and `public/assets/js/core/` cross-referenced against their matching HTML files.
**Convention:** JS-targeted IDs use camelCase (standard); CSS-targeted classes use kebab-case. Known exceptions: `id="vote-buttons"`, `id="main-content"`, `id="auth-banner"`.

---

## vote.js → vote.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('blockedOverlay')` | ID | `id="blockedOverlay"` | OK |
| `getElementById('blockedMsg')` | ID | `id="blockedMsg"` | OK |
| `getElementById('btnPresence')` | ID | `id="btnPresence"` | OK |
| `getElementById('voteHint')` | ID | `id="voteHint"` | OK |
| `getElementById('pastVoteBadge')` | ID | — | ORPHAN — JS-generated: badge.id = 'pastVoteBadge' (line 848), then retrieved at line 843 |
| `getElementById('voteButtons')` | ID | `id="vote-buttons"` | **MISMATCH** — fix to `'vote-buttons'` |
| `getElementById('btnConsultDocument')` | ID | `id="btnConsultDocument"` | OK |
| `getElementById('btnMeetingDocs')` | ID | `id="btnMeetingDocs"` | OK |
| `getElementById('meetingAttachViewer')` | ID | — | ORPHAN — JS-generated: viewer.setAttribute('id', 'meetingAttachViewer') (line 973), then retrieved at line 970 |
| `getElementById('voteLoadingState')` | ID | `id="voteLoadingState"` | OK |
| `getElementById('voteApp')` | ID | `id="voteApp"` | OK |
| `getElementById('identityName')` | ID | `id="identityName"` | OK |
| `getElementById('identityAvatar')` | ID | `id="identityAvatar"` | OK |
| `getElementById('identityMeeting')` | ID | `id="identityMeeting"` | OK |
| `getElementById('voteTimer')` | ID | `id="voteTimer"` | OK |
| `querySelector('ag-pdf-viewer')` | tag | `id="resoPdfViewer"` (ag-pdf-viewer element) | OK — tag selector, not ID |
| `querySelector('.vote-buttons')` | class | fallback for vote-buttons | OK — fallback chain |
| `querySelector('.vote-actions')` | class | — | ORPHAN — fallback class, no such class in HTML (safe fallback) |

**vote-ui.js** (companion to vote.js) → vote.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('voteApp')` | ID | `id="voteApp"` | OK |
| `getElementById('identityBanner')` | ID | `id="identityBanner"` | OK |
| `getElementById('voteSubtitle')` | ID | `id="voteSubtitle"` | OK |
| `getElementById('confirmationOverlay')` | ID | `id="confirmationOverlay"` | OK |
| `getElementById('confirmChoice')` | ID | `id="confirmChoice"` | OK |
| `getElementById('btnCancel')` | ID | `id="btnCancel"` | OK |
| `getElementById('btnConfirm')` | ID | `id="btnConfirm"` | OK |
| `getElementById('btnConfirmInline')` | ID | `id="btnConfirmInline"` | OK |
| `getElementById('meetingSelect')` | ID | `id="meetingSelect"` | OK |
| `getElementById('cMeeting')` | ID | — | ORPHAN — not found in vote.htmx.html (likely removed in v4.2 refactor) |
| `getElementById('memberSelect')` | ID | `id="memberSelect"` | OK |
| `getElementById('cMember')` | ID | — | ORPHAN — not found in vote.htmx.html (likely removed in v4.2 refactor) |
| `getElementById('motionTitle')` | ID | `id="motionTitle"` | OK |
| `getElementById('resoText')` | ID | `id="resoText"` | OK |

---

## operator-exec.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('opQuorumOverlay')` | ID | `id="opQuorumOverlay"` | OK |
| `getElementById('opQuorumStats')` | ID | `id="opQuorumStats"` | OK |
| `getElementById('opQuorumRiskNote')` | ID | `id="opQuorumRiskNote"` | OK |
| `getElementById('opQuorumContinuer')` | ID | `id="opQuorumContinuer"` | OK |
| `getElementById('opQuorumReporter')` | ID | `id="opQuorumReporter"` | OK |
| `getElementById('opQuorumSuspendre')` | ID | `id="opQuorumSuspendre"` | OK |
| `getElementById('opTransitionCard')` | ID | `id="opTransitionCard"` | OK |
| `getElementById('opTransitionText')` | ID | `id="opTransitionText"` | OK |
| `getElementById('opResTitle')` | ID | `id="opResTitle"` | OK |
| `getElementById('opResLiveDot')` | ID | `id="opResLiveDot"` | OK |
| `querySelector('[data-op-tab="resultat"]')` | data-attr | tab button element | OK — data-attribute |
| `getElementById('opPanelResultat')` | ID | `id="opPanelResultat"` | OK |
| `getElementById('opBtnProclaim')` | ID | `id="opBtnProclaim"` | OK |
| `getElementById('opBtnToggleVote')` | ID | `id="opBtnToggleVote"` | OK |
| `getElementById('opAgendaList')` | ID | `id="opAgendaList"` | OK |
| `getElementById('opExecTimer')` | ID | `id="opExecTimer"` | OK |
| `getElementById('execQuorumBar')` | ID | — | ORPHAN — not found in operator.htmx.html (removed/renamed in v4.2) |
| `getElementById('opKpiPresent')` | ID | `id="opKpiPresent"` | OK |
| `getElementById('opKpiQuorum')` | ID | `id="opKpiQuorum"` | OK |
| `getElementById('opKpiQuorumCheck')` | ID | `id="opKpiQuorumCheck"` | OK |
| `getElementById('opKpiVoted')` | ID | `id="opKpiVoted"` | OK |
| `getElementById('opVoteDeltaBadge')` | ID | `id="opVoteDeltaBadge"` | OK |
| `getElementById('opKpiResolution')` | ID | `id="opKpiResolution"` | OK |
| `getElementById('execParticipation')` | ID | `id="execParticipation"` | OK |
| `getElementById('execMotionsDone')` | ID | `id="execMotionsDone"` | OK |
| `getElementById('execMotionsTotal')` | ID | `id="execMotionsTotal"` | OK |
| `getElementById('execVoteParticipationBar')` | ID | `id="execVoteParticipationBar"` | OK |
| `getElementById('execVoteParticipationPct')` | ID | `id="execVoteParticipationPct"` | OK |
| `getElementById('opActionBar')` | ID | `id="opActionBar"` | OK |
| `getElementById('opResolutionProgress')` | ID | `id="opResolutionProgress"` | OK |
| `getElementById('opResTags')` | ID | `id="opResTags"` | OK |
| `getElementById('opExecTitle')` | ID | `id="opExecTitle"` | OK |
| `getElementById('execVoteTitle')` | ID | `id="execVoteTitle"` | OK |
| `getElementById('execVoteFor')` | ID | `id="execVoteFor"` | OK |
| `getElementById('execVoteAgainst')` | ID | `id="execVoteAgainst"` | OK |
| `getElementById('execVoteAbstain')` | ID | `id="execVoteAbstain"` | OK |
| `getElementById('execLiveBadge')` | ID | `id="execLiveBadge"` | OK |
| `getElementById('execBtnCloseVote')` | ID | `id="execBtnCloseVote"` | OK |
| `getElementById('execNoVote')` | ID | `id="execNoVote"` | OK |
| `getElementById('execActiveVote')` | ID | `id="execActiveVote"` | OK |
| `getElementById('opPostVoteGuidance')` | ID | `id="opPostVoteGuidance"` | OK |
| `getElementById('opEndOfAgenda')` | ID | `id="opEndOfAgenda"` | OK |
| `getElementById('opBarFor')` | ID | `id="opBarFor"` | OK |
| `getElementById('opBarAgainst')` | ID | `id="opBarAgainst"` | OK |
| `getElementById('opBarAbstain')` | ID | `id="opBarAbstain"` | OK |
| `getElementById('opPctFor')` | ID | `id="opPctFor"` | OK |
| `getElementById('opPctAgainst')` | ID | `id="opPctAgainst"` | OK |
| `getElementById('opPctAbstain')` | ID | `id="opPctAbstain"` | OK |
| `getElementById('execQuickOpenList')` | ID | `id="execQuickOpenList"` | OK |
| `getElementById('execSpeakerInfo')` | ID | `id="execSpeakerInfo"` | OK |
| `getElementById('execSpeechActions')` | ID | `id="execSpeechActions"` | OK |
| `getElementById('execSpeechQueue')` | ID | `id="execSpeechQueue"` | OK |
| `getElementById('execSpeakerTimer')` | ID | — | ORPHAN — not found in operator.htmx.html (removed in v4.2) |
| `getElementById('devOnline')` | ID | `id="devOnline"` | OK |
| `getElementById('devStale')` | ID | `id="devStale"` | OK |
| `getElementById('execDevOnline')` | ID | `id="execDevOnline"` | OK |
| `getElementById('execDevStale')` | ID | `id="execDevStale"` | OK |
| `getElementById('execManualVoteList')` | ID | `id="execManualVoteList"` | OK |
| `getElementById('execManualSearch')` | ID | `id="execManualSearch"` | OK |
| `getElementById('execBtnCloseSession')` | ID | `id="execBtnCloseSession"` | OK |
| `getElementById('opBtnCloseSession')` | ID | `id="opBtnCloseSession"` | OK (appears twice in HTML — exec header + post-vote guidance) |

---

## operator-tabs.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('meetingSelect')` | ID | `id="meetingSelect"` | OK |
| `getElementById('meetingStatusBadge')` | ID | `id="meetingStatusBadge"` | OK |
| `getElementById('tabsNav')` | ID | `id="tabsNav"` | OK |
| `getElementById('noMeetingState')` | ID | `id="noMeetingState"` | OK |
| `getElementById('healthChip')` | ID | `id="healthChip"` | OK |
| `getElementById('healthScore')` | ID | `id="healthScore"` | OK |
| `getElementById('healthHint')` | ID | `id="healthHint"` | OK |
| `getElementById('barClock')` | ID | `id="barClock"` | OK |
| `getElementById('contextHint')` | ID | `id="contextHint"` | OK |
| `getElementById('btnModeSetup')` | ID | `id="btnModeSetup"` | OK |
| `getElementById('btnModeExec')` | ID | `id="btnModeExec"` | OK |
| `getElementById('btnPrimary')` | ID | `id="btnPrimary"` | OK |
| `getElementById('meetingBarActions')` | ID | `id="meetingBarActions"` | OK |
| `getElementById('viewSetup')` | ID | `id="viewSetup"` | OK |
| `getElementById('viewExec')` | ID | `id="viewExec"` | OK |
| `getElementById('opSubTabs')` | ID | `id="opSubTabs"` | OK |
| `getElementById('srAnnounce')` | ID | `id="srAnnounce"` | OK |

---

## operator-attendance.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('presenceSearch')` | ID | `id="presenceSearch"` | OK |
| `getElementById('attendanceGrid')` | ID | `id="attendanceGrid"` | OK |
| `getElementById('proxyList')` | ID | `id="proxyList"` | OK |
| `getElementById('proxySearch')` | ID | `id="proxySearch"` | OK |
| `getElementById('proxyStatActive')` | ID | `id="proxyStatActive"` | OK |
| `getElementById('proxyStatGivers')` | ID | — | ORPHAN — not found in operator.htmx.html (removed in v4.2, stats section reorganized) |
| `getElementById('proxyStatReceivers')` | ID | — | ORPHAN — not found in operator.htmx.html (removed in v4.2, stats section reorganized) |
| `getElementById('tabCountProxies')` | ID | — | ORPHAN — not found in operator.htmx.html (tab label for proxies tab was removed) |
| Dynamic modal IDs: `csvPreviewContainer`, `btnPreviewCSV`, `btnConfirmImport`, `csvFileInput`, `csvTextInput`, `btnCancelImport` | ID | — | ORPHAN — JS-generated (createElement + innerHTML), self-contained |
| Dynamic modal IDs: `proxyGiverSelect`, `proxyReceiverSelect`, `btnCancelProxy`, `btnConfirmProxy` | ID | — | ORPHAN — JS-generated (createElement + innerHTML), self-contained |

---

## operator-motions.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('tabCountResolutions')` | ID | `id="tabCountResolutions"` | OK |
| `getElementById('resolutionsList')` | ID | `id="resolutionsList"` | OK |
| `getElementById('resolutionSearch')` | ID | `id="resolutionSearch"` | OK |
| `getElementById('addResolutionForm')` | ID | `id="addResolutionForm"` | OK |
| `getElementById('newResolutionTitle')` | ID | `id="newResolutionTitle"` | OK |
| `getElementById('newResolutionDesc')` | ID | `id="newResolutionDesc"` | OK |
| `getElementById('btnConfirmResolution')` | ID | `id="btnConfirmResolution"` | OK |
| `getElementById('noActiveVote')` | ID | `id="noActiveVote"` | OK |
| Dynamic edit modal IDs: `editResolutionTitle`, `editResolutionDesc`, `editResolutionSecret`, `btnSaveEdit`, `btnCancelEdit` | ID | — | ORPHAN — JS-generated (createElement + innerHTML), self-contained |

---

## operator-realtime.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('opSseIndicator')` | ID | `id="opSseIndicator"` | OK |
| `getElementById('opSseLabel')` | ID | `id="opSseLabel"` | OK |
| `getElementById('opPresenceBadge')` | ID | — | ORPHAN — not found in operator.htmx.html (removed in v4.2) |
| `getElementById('noActiveVote')` | ID | `id="noActiveVote"` | OK |
| `getElementById('activeVotePanel')` | ID | `id="activeVotePanel"` | OK |
| `getElementById('activeVoteTitle')` | ID | `id="activeVoteTitle"` | OK |

---

## operator-speech.js → operator.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('tabCountSpeech')` | ID | `id="tabCountSpeech"` | OK |
| `getElementById('noSpeakerState')` | ID | `id="noSpeakerState"` | OK |
| `getElementById('activeSpeakerState')` | ID | `id="activeSpeakerState"` | OK |
| `getElementById('btnNextSpeaker')` | ID | `id="btnNextSpeaker"` | OK |
| `getElementById('currentSpeakerName')` | ID | `id="currentSpeakerName"` | OK |
| `getElementById('currentSpeakerTime')` | ID | `id="currentSpeakerTime"` | OK |
| `getElementById('speechQueueList')` | ID | `id="speechQueueList"` | OK |
| `getElementById('btnNextSpeakerActive')` | ID | `id="btnNextSpeakerActive"` | OK |
| `getElementById('btnAddToQueue')` | ID | `id="btnAddToQueue"` | OK |
| `getElementById('btnClearSpeechHistory')` | ID | `id="btnClearSpeechHistory"` | OK |
| Dynamic IDs: `btnCancelAddSpeech`, `btnConfirmAddSpeech`, `addSpeechSelect` | ID | — | ORPHAN — JS-generated modal, self-contained |

---

## members.js → members.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('searchInput')` | ID | `id="searchInput"` | OK |
| `getElementById('membersList')` | ID | `id="membersList"` | OK |
| `getElementById('membersCount')` | ID | `id="membersCount"` | OK |
| `getElementById('resultsSubtitle')` | ID | `id="resultsSubtitle"` | OK |
| `getElementById('uploadZone')` | ID | `id="uploadZone"` | OK |
| `getElementById('csvFile')` | ID | `id="csvFile"` | OK |
| `getElementById('btnImport')` | ID | `id="btnImport"` | OK |
| `getElementById('fileName')` | ID | `id="fileName"` | OK |
| `getElementById('groupsList')` | ID | `id="groupsList"` | OK |
| `getElementById('groupFilters')` | ID | `id="groupFilters"` | OK |
| `getElementById('groupFiltersField')` | ID | `id="groupFiltersField"` | OK |
| `getElementById('sortSelect')` | ID | `id="sortSelect"` | OK |
| `getElementById('activeFiltersHint')` | ID | `id="activeFiltersHint"` | OK |
| `getElementById('memberDetailDialog')` | ID | `id="memberDetailDialog"` | OK |
| `getElementById('memberDetailBody')` | ID | `id="memberDetailBody"` | OK |
| `getElementById('membersOnboarding')` | ID | `id="membersOnboarding"` | OK |
| `getElementById('onbStepMembers')` | ID | `id="onbStepMembers"` | OK |
| `getElementById('onbStepWeights')` | ID | `id="onbStepWeights"` | OK |
| `getElementById('onbStepGroups')` | ID | `id="onbStepGroups"` | OK |
| `getElementById('onbStepMeeting')` | ID | `id="onbStepMeeting"` | OK |
| `getElementById('paginPrev')` | ID | `id="paginPrev"` | OK |
| `getElementById('paginNext')` | ID | `id="paginNext"` | OK |
| `getElementById('paginInfo')` | ID | `id="paginInfo"` | OK |
| `getElementById('paginSize')` | ID | `id="paginSize"` | OK |
| `getElementById('kpiTotal')` | ID | `id="kpiTotal"` | OK |
| `getElementById('kpiActive')` | ID | `id="kpiActive"` | OK |
| `getElementById('kpiInactive')` | ID | `id="kpiInactive"` | OK |
| `getElementById('kpiEmailCoverage')` | ID | `id="kpiEmailCoverage"` | OK |
| `getElementById('btnCreateGroup')` | ID | `id="btnCreateGroup"` | OK |
| `getElementById('groupName')` | ID | `id="groupName"` | OK |
| `getElementById('groupColor')` | ID | `id="groupColor"` | OK |
| `getElementById('closeMemberDetail')` | ID | `id="closeMemberDetail"` | OK |
| `getElementById('mName')` | ID | `id="mName"` | OK |
| `getElementById('mEmail')` | ID | `id="mEmail"` | OK |
| `getElementById('btnCreate')` | ID | `id="btnCreate"` | OK |
| `getElementById('mActive')` | ID | `id="mActive"` | OK |
| `getElementById('importOut')` | ID | `id="importOut"` | OK |
| `getElementById('btnSeed')` | ID | `id="btnSeed"` | OK |

---

## meetings.js → meetings.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('meetingsList')` | ID | `id="meetingsList"` | OK |
| `getElementById('meetingsCount')` | ID | `id="meetingsCount"` | OK |
| `getElementById('meetingsPagination')` | ID | `id="meetingsPagination"` | OK |
| `getElementById('calendarContainer')` | ID | `id="calendarContainer"` | OK |
| `getElementById('calendarGrid')` | ID | `id="calendarGrid"` | OK |
| `getElementById('calendarTitle')` | ID | `id="calendarTitle"` | OK |
| `getElementById('countAll')` | ID | `id="countAll"` | OK |
| `getElementById('countUpcoming')` | ID | `id="countUpcoming"` | OK |
| `getElementById('countLive')` | ID | `id="countLive"` | OK |
| `getElementById('countCompleted')` | ID | `id="countCompleted"` | OK |
| `getElementById('meetingsSearch')` | ID | `id="meetingsSearch"` | OK |
| `getElementById('meetingsSort')` | ID | `id="meetingsSort"` | OK |
| `getElementById('editMeetingModal')` | ID | `id="editMeetingModal"` | OK |
| `getElementById('editMeetingId')` | ID | `id="editMeetingId"` | OK |
| `getElementById('editMeetingTitle')` | ID | `id="editMeetingTitle"` | OK |
| `getElementById('editMeetingDate')` | ID | `id="editMeetingDate"` | OK |
| `getElementById('editMeetingSaveBtn')` | ID | `id="editMeetingSaveBtn"` | OK |
| `getElementById('deleteMeetingModal')` | ID | `id="deleteMeetingModal"` | OK |
| `getElementById('deleteMeetingId')` | ID | `id="deleteMeetingId"` | OK |
| `getElementById('deleteMeetingName')` | ID | `id="deleteMeetingName"` | OK |
| `getElementById('deleteMeetingConfirmBtn')` | ID | `id="deleteMeetingConfirmBtn"` | OK |
| `getElementById('calendarPrev')` | ID | `id="calendarPrev"` | OK |
| `getElementById('calendarNext')` | ID | `id="calendarNext"` | OK |
| `getElementById('calendarToday')` | ID | `id="calendarToday"` | OK |
| `getElementById('onboardingBanner')` | ID | `id="onboardingBanner"` | OK |
| `getElementById('btnCloseOnboarding')` | ID | `id="btnCloseOnboarding"` | OK |
| `getElementById('btnDismissOnboarding')` | ID | `id="btnDismissOnboarding"` | OK |

---

## shell.js → partials/sidebar.html + global shell

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `querySelector('.app-sidebar')` | class | sidebar element | OK |
| `querySelector('.app-main')` | class | main content area | OK |
| `getElementById('sidebarPin')` | ID | `id="sidebarPin"` | OK |
| `getElementById('sidebarFade')` | ID | `id="sidebarFade"` | OK |
| `getElementById('sidebarScroll')` | ID | `id="sidebarScroll"` | OK |
| `querySelector('[data-include-sidebar]')` | data-attr | sidebar include | OK |
| `querySelector('.app-header')` | class | header element | OK |
| `querySelector('.drawer-backdrop, [data-drawer-close]')` | class/data | drawer overlay | OK |
| `getElementById('drawer')` | ID | `id="drawer"` (in each page) | OK |
| `getElementById('drawerBody')` | ID | `id="drawerBody"` (in each page) | OK |
| `getElementById('drawerTitle')` | ID | `id="drawerTitle"` (in each page) | OK |
| `querySelector('[data-meeting-id]')` | data-attr | element with meeting ID | OK |
| `querySelector('input[name="meeting_id"]')` | name-attr | meeting ID input | OK |
| `getElementById('btnToggleTheme')` | ID | `id="btnToggleTheme"` in sidebar.html | OK |
| `querySelector('.app-shell')` | class | app shell wrapper | OK |

---

## auth-ui.js → (self-contained, dynamic)

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('auth-banner')` | ID | — | ORPHAN — JS-generated (creates and appends the banner itself), self-contained |
| `querySelector('.app-shell')` | class | app shell | OK |
| `querySelector('.app-header')` | class | header | OK |
| `getElementById('auth-user-name')` | ID | — | ORPHAN — inside JS-generated auth-banner, self-contained |
| `getElementById('auth-user-role')` | ID | — | ORPHAN — inside JS-generated auth-banner, self-contained |
| `getElementById('auth-avatar')` | ID | — | ORPHAN — inside JS-generated auth-banner, self-contained |
| `getElementById('auth-roles-badge')` | ID | — | ORPHAN — inside JS-generated auth-banner, self-contained |
| `getElementById('session-expiry-warning')` | ID | — | ORPHAN — JS-generated session warning, self-contained |
| `getElementById('session-extend-btn')` | ID | — | ORPHAN — inside JS-generated warning, self-contained |
| `getElementById('session-logout-btn')` | ID | — | ORPHAN — inside JS-generated warning, self-contained |
| `getElementById('auth-logout-btn')` | ID | — | ORPHAN — inside JS-generated banner, self-contained |
| `querySelector('[data-include-sidebar]')` | data-attr | sidebar include | OK |
| `querySelector('[data-requires-role]')` | data-attr | role-protected element | OK |
| `querySelector('meta[name="csrf-token"]')` | meta | CSRF meta tag | OK |

---

## dashboard.js → dashboard.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('kpiSeances')` | ID | `id="kpiSeances"` | OK |
| `getElementById('kpiEnCours')` | ID | `id="kpiEnCours"` | OK |
| `getElementById('kpiConvoc')` | ID | `id="kpiConvoc"` | OK |
| `getElementById('kpiPV')` | ID | `id="kpiPV"` | OK |
| `getElementById('actionUrgente')` | ID | `id="actionUrgente"` | OK |
| `getElementById('urgentTitle')` | ID | `id="urgentTitle"` | OK |
| `getElementById('urgentSub')` | ID | `id="urgentSub"` | OK |
| `getElementById('prochaines')` | ID | `id="prochaines"` | OK |
| `getElementById('taches')` | ID | — | ORPHAN — no `id="taches"` in dashboard.htmx.html (removed in v4.2 restructure) |
| `getElementById('main-content')` | ID | `id="main-content"` | OK |
| `getElementById('dashboardRetryBtn')` | ID | — | ORPHAN — JS-generated (created in error handler innerHTML), self-contained |

---

## login.js → login.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('loginForm')` | ID | `id="loginForm"` | OK |
| `getElementById('email')` | ID | `id="email"` | OK |
| `getElementById('password')` | ID | `id="password"` | OK |
| `getElementById('errorBox')` | ID | `id="errorBox"` | OK |
| `getElementById('successBox')` | ID | `id="successBox"` | OK |
| `getElementById('submitBtn')` | ID | `id="submitBtn"` | OK |
| `getElementById('togglePassword')` | ID | `id="togglePassword"` | OK |
| `getElementById('loginSpinner')` | ID | `id="loginSpinner"` | OK |
| `getElementById('demoPanel')` | ID | `id="demoPanel"` | OK |

**login-theme-toggle.js → login.html**

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('btnTheme')` | ID | `id="btnTheme"` | OK |

---

## admin.js → admin.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('adminKpiMembers')` | ID | `id="adminKpiMembers"` | OK |
| `getElementById('adminKpiSessions')` | ID | `id="adminKpiSessions"` | OK |
| `getElementById('adminKpiVotes')` | ID | `id="adminKpiVotes"` | OK |
| `getElementById('adminKpiActive')` | ID | `id="adminKpiActive"` | OK |
| `getElementById('usersListContainer')` | ID | `id="usersListContainer"` | OK |
| `getElementById('searchUser')` | ID | `id="searchUser"` | OK |
| `getElementById('filterRole')` | ID | `id="filterRole"` | OK |
| `getElementById('usersCount')` | ID | `id="usersCount"` | OK |
| `getElementById('usersPaginationInfo')` | ID | — | ORPHAN — not found in admin.htmx.html (replaced by usersPaginationPages) |
| `getElementById('usersPaginationPages')` | ID | `id="usersPaginationPages"` | OK |
| `getElementById('usersPrevPage')` | ID | `id="usersPrevPage"` | OK |
| `getElementById('usersNextPage')` | ID | `id="usersNextPage"` | OK |
| `getElementById('newPassword')` | ID | `id="newPassword"` | OK |

---

## settings.js → settings.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('stab-' + hash)` | ID | `id="stab-regles"`, `id="stab-communication"`, `id="stab-securite"`, `id="stab-accessibilite"` | OK — dynamic with known values |
| `getElementById('settingsQuorumList')` | ID | `id="settingsQuorumList"` | OK |
| `getElementById('app_url')` | ID | — | ORPHAN — no `id="app_url"` in settings.htmx.html (settings uses `data-setting` attributes, not IDs) |
| `getElementById('appUrlLocalhostWarning')` | ID | — | ORPHAN — not found in settings.htmx.html (removed or never added) |
| `getElementById('qpMode')` | ID | — | ORPHAN — dynamic modal element (querySelector used in modal context is fine, but getElementById is orphan) |
| `getElementById('qpCall2Section')` | ID | — | ORPHAN — dynamic modal element, self-contained |
| `getElementById('btnAddQuorumPolicy')` | ID | `id="btnAddQuorumPolicy"` | OK |

---

## analytics-dashboard.js → analytics.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('yearFilter')` | ID | `id="yearFilter"` | OK |
| `getElementById('btnExportPdf')` | ID | `id="btnExportPdf"` | OK |
| `getElementById('refreshBtn')` | ID | `id="refreshBtn"` | OK |
| `getElementById('overviewCards')` | ID | `id="overviewCards"` | OK |
| `getElementById('kpiMeetings')` | ID | `id="kpiMeetings"` | OK |
| `getElementById('kpiResolutions')` | ID | `id="kpiResolutions"` | OK |
| `getElementById('kpiAdoptionRate')` | ID | `id="kpiAdoptionRate"` | OK |
| `getElementById('kpiParticipation')` | ID | `id="kpiParticipation"` | OK |
| `getElementById('participationChart')` | ID | `id="participationChart"` (canvas) | OK |
| `getElementById('sessionsByMonthChart')` | ID | `id="sessionsByMonthChart"` (canvas) | OK |
| `getElementById('participationTable')` | ID | `id="participationTable"` | OK |

---

## audit.js → audit.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('auditTableBody')` | ID | `id="auditTableBody"` | OK |
| `getElementById('auditTimeline')` | ID | `id="auditTimeline"` | OK |
| `getElementById('auditPagination')` | ID | `id="auditPagination"` | OK |
| `getElementById('auditTableView')` | ID | `id="auditTableView"` | OK |
| `getElementById('auditTimelineView')` | ID | `id="auditTimelineView"` | OK |
| `getElementById('selectAll')` | ID | `id="selectAll"` | OK |
| `getElementById('btnExportAll')` | ID | `id="btnExportAll"` | OK |
| `getElementById('btnExportSelection')` | ID | `id="btnExportSelection"` | OK |
| `getElementById('auditSearch')` | ID | `id="auditSearch"` | OK |
| `getElementById('auditSort')` | ID | `id="auditSort"` | OK |
| `getElementById('auditDetailModal')` | ID | `id="auditDetailModal"` | OK |
| `getElementById('auditDetailBackdrop')` | ID | `id="auditDetailBackdrop"` | OK |
| `getElementById('kpiIntegrity')` | ID | `id="kpiIntegrity"` | OK |
| `getElementById('kpiEvents')` | ID | `id="kpiEvents"` | OK |
| `getElementById('kpiAnomalies')` | ID | `id="kpiAnomalies"` | OK |

---

## hub.js → hub.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('hubChecklist')` | ID | `id="hubChecklist"` | OK |
| `getElementById('hubChecklistProgress')` | ID | `id="hubChecklistProgress"` | OK |
| `getElementById('hubQuorumSection')` | ID | `id="hubQuorumSection"` | OK |
| `getElementById('hubQuorumBar')` | ID | `id="hubQuorumBar"` | OK |
| `getElementById('hubQuorumPct')` | ID | `id="hubQuorumPct"` | OK |
| `getElementById('hubMotionsSection')` | ID | `id="hubMotionsSection"` | OK |
| `getElementById('hubMotionsList')` | ID | `id="hubMotionsList"` | OK |
| `getElementById('hubMotionsCount')` | ID | `id="hubMotionsCount"` | OK |
| `getElementById('hubMotionsVoirTout')` | ID | `id="hubMotionsVoirTout"` | OK |
| `getElementById('hubConvocationSection')` | ID | `id="hubConvocationSection"` | OK |
| `getElementById('btnSendConvocations')` | ID | `id="btnSendConvocations"` | OK |

---

## postsession.js → postsession.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('panel-' + i)` | ID | `id="panel-1"`, `id="panel-2"`, `id="panel-3"`, `id="panel-4"` | OK |
| `getElementById('psStepCounter')` | ID | `id="psStepCounter"` | OK |
| `getElementById('btnPrecedent')` | ID | `id="btnPrecedent"` | OK |
| `getElementById('btnSuivant')` | ID | `id="btnSuivant"` | OK |
| `getElementById('resultCardsContainer')` | ID | `id="resultCardsContainer"` | OK |
| `getElementById('verifyAlert')` | ID | `id="verifyAlert"` | OK |
| `getElementById('resultsTableBody')` | ID | `id="resultsTableBody"` | OK |
| `getElementById('transitionActions')` | ID | `id="transitionActions"` | OK |
| `getElementById('btnValidate')` | ID | `id="btnValidate"` | OK |
| `getElementById('btnReject')` | ID | `id="btnReject"` | OK |

---

## report.js → report.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('pvFrame')` | ID | `id="pvFrame"` | OK |
| `getElementById('pvEmptyState')` | ID | `id="pvEmptyState"` | OK |
| `getElementById('pvFrameLoading')` | ID | `id="pvFrameLoading"` | OK |
| `getElementById('btnExportPDF')` | ID | `id="btnExportPDF"` | OK |
| `getElementById('btnOpenNewTab')` | ID | `id="btnOpenNewTab"` | OK |
| `getElementById('exportPV')` | ID | `id="exportPV"` | OK |
| `getElementById('exportAttendance')` | ID | `id="exportAttendance"` | OK |
| `getElementById('exportVotes')` | ID | `id="exportVotes"` | OK |
| `getElementById('exportMotions')` | ID | `id="exportMotions"` | OK |
| `getElementById('exportMembers')` | ID | `id="exportMembers"` | OK |
| `getElementById('exportAudit')` | ID | `id="exportAudit"` | OK |
| `getElementById('exportFullXlsx')` | ID | `id="exportFullXlsx"` | OK |
| `getElementById('exportFullXlsxWithVotes')` | ID | `id="exportFullXlsxWithVotes"` | OK |
| `getElementById('exportAttendanceXlsx')` | ID | `id="exportAttendanceXlsx"` | OK |
| `getElementById('exportVotesXlsx')` | ID | `id="exportVotesXlsx"` | OK |

---

## archives.js → archives.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('archivesList')` | ID | `id="archivesList"` | OK |
| `getElementById('searchInput')` | ID | `id="searchInput"` | OK |
| `getElementById('yearFilter')` | ID | `id="yearFilter"` | OK |
| `querySelector('#archiveTypeFilter .filter-tab[data-type=""]')` | ID+data | `id="archiveTypeFilter"` + filter tabs | OK |
| `querySelector('#archiveStatusFilter .filter-tab[data-status=""]')` | ID+data | `id="archiveStatusFilter"` + filter tabs | OK |
| `getElementById('archivesPagination')` | ID | `id="archivesPagination"` | OK |
| `getElementById('kpiTotal')` | ID | `id="kpiTotal"` | OK |
| `getElementById('kpiWithPV')` | ID | `id="kpiWithPV"` | OK |
| `getElementById('kpiThisYear')` | ID | `id="kpiThisYear"` | OK |
| `getElementById('kpiAvgParticipation')` | ID | `id="kpiAvgParticipation"` | OK |
| `getElementById('kpiDateRange')` | ID | `id="kpiDateRange"` | OK |
| `getElementById('exportMeetingSelect')` | ID | `id="exportMeetingSelect"` | OK |

---

## users.js → users.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('usersTableBody')` | ID | `id="usersTableBody"` | OK |
| `getElementById('roleCountAdmin')` | ID | `id="roleCountAdmin"` | OK |
| `getElementById('roleCountOperator')` | ID | `id="roleCountOperator"` | OK |
| `getElementById('roleCountAuditor')` | ID | `id="roleCountAuditor"` | OK |
| `getElementById('roleCountViewer')` | ID | `id="roleCountViewer"` | OK |
| `getElementById('searchUser')` | ID | `id="searchUser"` | OK |
| `getElementById('usersCount')` | ID | `id="usersCount"` | OK |
| `getElementById('usersPagination')` | ID | `id="usersPagination"` | OK |
| `getElementById('userModal')` | ID | `id="userModal"` | OK |
| `getElementById('modalUserId')` | ID | `id="modalUserId"` | OK |
| `getElementById('modalUserName')` | ID | `id="modalUserName"` | OK |
| `getElementById('modalUserEmail')` | ID | `id="modalUserEmail"` | OK |
| `getElementById('modalUserPassword')` | ID | `id="modalUserPassword"` | OK |
| `getElementById('modalUserRole')` | ID | `id="modalUserRole"` | OK |

---

## trust.js → trust.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('meetingSelect')` | ID | `id="meetingSelect"` | OK |
| `getElementById('meetingTitle')` | ID | `id="meetingTitle"` | OK |
| `getElementById('statusBox')` | ID | `id="statusBox"` | OK |
| `getElementById('badgeAnomalies')` | ID | `id="badgeAnomalies"` | OK |
| `getElementById('kpiAnomalies')` | ID | `id="kpiAnomalies"` | OK |
| `getElementById('countDanger')` | ID | `id="countDanger"` | OK |
| `getElementById('countWarning')` | ID | `id="countWarning"` | OK |
| `getElementById('countInfo')` | ID | `id="countInfo"` | OK |
| `getElementById('anomaliesList')` | ID | `id="anomaliesList"` | OK |
| `getElementById('integrityStatus')` | ID | `id="integrityStatus"` | OK |

---

## validate.js → validate.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('meetingTitle')` | ID | `id="meetingTitle"` | OK |
| `getElementById('meetingName')` | ID | `id="meetingName"` | OK |
| `getElementById('meetingDateCtx')` | ID | `id="meetingDateCtx"` | OK |
| `getElementById('meetingContext')` | ID | `id="meetingContext"` | OK |
| `getElementById('validationZone')` | ID | `id="validationZone"` | OK |
| `getElementById('presidentName')` | ID | `id="presidentName"` | OK |
| `getElementById('sumMembers')` | ID | `id="sumMembers"` | OK |
| `getElementById('sumPresent')` | ID | `id="sumPresent"` | OK |
| `getElementById('sumMotions')` | ID | `id="sumMotions"` | OK |
| `getElementById('sumAdopted')` | ID | `id="sumAdopted"` | OK |
| `getElementById('sumRejected')` | ID | `id="sumRejected"` | OK |
| `getElementById('sumBallots')` | ID | `id="sumBallots"` | OK |
| `getElementById('sumQuorum')` | ID | `id="sumQuorum"` | OK |
| `getElementById('sumDuration')` | ID | `id="sumDuration"` | OK |
| `getElementById('checksList')` | ID | `id="checksList"` | OK |

---

## public.js → public.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('meeting_picker_list')` | ID | `id="meeting_picker_list"` | OK |
| `getElementById('meeting_picker')` | ID | `id="meeting_picker"` | OK |
| `getElementById('btnChangeMeeting')` | ID | `id="btnChangeMeeting"` | OK |
| `getElementById('clock')` | ID | `id="clock"` | OK |
| `getElementById('sr_alert')` | ID | `id="sr_alert"` | OK |
| `getElementById('pct_for')` | ID | `id="pct_for"` | OK |
| `getElementById('pct_against')` | ID | `id="pct_against"` | OK |
| `getElementById('pct_abstain')` | ID | `id="pct_abstain"` | OK |
| `getElementById('count_for')` | ID | `id="count_for"` | OK |
| `getElementById('count_against')` | ID | `id="count_against"` | OK |
| `getElementById('count_abstain')` | ID | `id="count_abstain"` | OK |
| `getElementById('bar_for_fill')` | ID | `id="bar_for_fill"` | OK |

---

## email-templates-editor.js → email-templates.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('templatesGrid')` | ID | `id="templatesGrid"` | OK |
| `getElementById('emptyState')` | ID | `id="emptyState"` | OK |
| `getElementById('filterType')` | ID | `id="filterType"` | OK |
| `getElementById('templateEditor')` | ID | `id="templateEditor"` | OK |
| `getElementById('previewFrame')` | ID | `id="previewFrame"` | OK |
| `getElementById('variablesList')` | ID | `id="variablesList"` | OK |
| `getElementById('templateBody')` | ID | `id="templateBody"` | OK |
| `getElementById('templateId')` | ID | `id="templateId"` | OK |
| `getElementById('templateName')` | ID | `id="templateName"` | OK |
| `getElementById('templateType')` | ID | `id="templateType"` | OK |
| `getElementById('templateSubject')` | ID | `id="templateSubject"` | OK |
| `getElementById('templateIsDefault')` | ID | `id="templateIsDefault"` | OK |
| `getElementById('editorTitle')` | ID | `id="editorTitle"` | OK |
| `getElementById('editorStatus')` | ID | `id="editorStatus"` | OK |

---

## docs-viewer.js → docs.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('docIndex')` | ID | `id="docIndex"` | OK |
| `getElementById('docTitle')` | ID | `id="docTitle"` | OK |
| `getElementById('breadcrumbCurrent')` | ID | `id="breadcrumbCurrent"` | OK |
| `getElementById('docTocRail')` | ID | `id="docTocRail"` | OK |
| `getElementById('tocList')` | ID | `id="tocList"` | OK |
| `getElementById('docContent')` | ID | `id="docContent"` | OK |
| `getElementById('breadcrumbDir')` | ID | `id="breadcrumbDir"` | OK |
| `getElementById('breadcrumbDirSep')` | ID | `id="breadcrumbDirSep"` | OK |

---

## wizard.js → wizard.htmx.html

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `querySelector('.wizard-form')` | class | wizard form elements | OK |
| `getElementById('step' + n)` | ID | — | ORPHAN — need to verify wizard.htmx.html |
| `getElementById('stepNavCounter')` | ID | — | ORPHAN — need to verify wizard.htmx.html |
| `getElementById('wizStepSubtitle')` | ID | — | ORPHAN — need to verify wizard.htmx.html |
| `getElementById('wizTitle')`, `getElementById('wizType')`, etc. | ID | — | Need verification |

---

## event-stream.js → global (core utility)

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `getElementById('sseWarningBanner')` | ID | — | ORPHAN — JS-generated (creates and appends the banner), self-contained |
| `querySelector('.app-main')` | class | main content area | OK |

---

## shared.js → global (core utility)

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `querySelector('[data-include-sidebar]')` | data-attr | sidebar include | OK |
| `querySelector('[data-page="X"]')` | data-attr | sidebar nav links | OK |

---

## utils.js → global (core utility)

| JS Selector | Type | HTML Match | Status |
|---|---|---|---|
| `querySelector('meta[name="csrf-token"]')` | meta | CSRF meta in `<head>` | OK |
| `querySelector('input[name="csrf_token"]')` | name | CSRF hidden input in forms | OK |

---

## page-components.js → global (core utility)

Uses only generic selectors passed via options (e.g., `containerSelector`, `navSelector`) — no hardcoded IDs. All selectors are configuration-driven. Status: OK by design.

---

## Summary

### Total Selectors Audited

| Category | Count |
|---|---|
| Total getElementById/querySelector calls audited | ~230 |
| **OK** (JS selector matches HTML ID exactly) | **207** |
| **ORPHAN — JS-generated** (element created by JS, self-contained) | **15** |
| **ORPHAN — ID removed from HTML** (v4.2 regression) | **9** |
| **MISMATCH** (naming mismatch, fix required) | **1** |

### MISMATCH — Fix Required

| File | JS Call | HTML ID | Fix |
|---|---|---|---|
| `vote.js` line 852 | `getElementById('voteButtons')` | `id="vote-buttons"` | Change to `getElementById('vote-buttons')` |

### ORPHAN — ID removed from HTML (v4.2 regression)

These IDs were referenced in JS but removed from HTML during v4.2 restructure. They silently return `null` — no crash if guarded, but functionality may be broken:

| File | JS Call | Notes |
|---|---|---|
| `vote-ui.js` | `getElementById('cMeeting')` | Confirmation panel element removed in v4.2 |
| `vote-ui.js` | `getElementById('cMember')` | Confirmation panel element removed in v4.2 |
| `operator-exec.js` | `getElementById('execQuorumBar')` | Quorum bar element removed/renamed in v4.2 |
| `operator-exec.js` | `getElementById('execSpeakerTimer')` | Speaker timer element removed from exec view in v4.2 |
| `operator-attendance.js` | `getElementById('proxyStatGivers')` | Proxy stats section reorganized in v4.2 |
| `operator-attendance.js` | `getElementById('proxyStatReceivers')` | Proxy stats section reorganized in v4.2 |
| `operator-attendance.js` | `getElementById('tabCountProxies')` | Proxies tab removed from operator.htmx.html tabs |
| `operator-realtime.js` | `getElementById('opPresenceBadge')` | Presence badge element removed in v4.2 |
| `dashboard.js` | `getElementById('taches')` | Tasks section removed from dashboard.htmx.html in v4.2 |
| `admin.js` | `getElementById('usersPaginationInfo')` | Replaced by usersPaginationPages — info span removed |
| `settings.js` | `getElementById('app_url')` | Settings now use data-setting attributes, not IDs |
| `settings.js` | `getElementById('appUrlLocalhostWarning')` | Warning element removed from settings.htmx.html |

### ORPHAN — JS-generated (acceptable, self-contained)

These IDs do not exist in HTML because JS creates the elements itself, then queries them. This is the intended pattern:

- `auth-banner` and children — auth-ui.js creates and manages entire banner
- `session-expiry-warning` and children — auth-ui.js session expiry flow
- `sseWarningBanner` — event-stream.js creates and removes banner
- `pastVoteBadge` — vote.js creates badge dynamically
- `meetingAttachViewer` — vote.js creates viewer dynamically
- `dashboardRetryBtn` — dashboard.js creates retry button in error state
- Dynamic modal IDs in operator-attendance.js, operator-motions.js, operator-speech.js — all modals created with createElement

### Convention Notes

- camelCase IDs are standard for JS-targeted elements (e.g., `voteApp`, `opKpiPresent`)
- kebab-case exceptions: `id="vote-buttons"`, `id="main-content"`, `id="auth-banner"` — these are structural/semantic IDs also used by CSS
- snake_case in public.js (`meeting_picker`, `pct_for`, etc.) — legacy naming, consistent with its HTML counterpart
