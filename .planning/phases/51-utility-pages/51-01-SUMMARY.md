---
phase: 51-utility-pages
plan: 01
subsystem: frontend-pages
tags: [help, faq, email-templates, v4.3-design, rebuild]
requires: []
provides: [help-page, email-templates-page]
affects: [help-faq.js, email-templates-editor.js]
tech_stack_added: []
tech_stack_patterns: [v4.3-page-title, gradient-accent-bar, token-based-css]
key_files_created: []
key_files_modified:
  - public/help.htmx.html
  - public/assets/css/help.css
  - public/email-templates.htmx.html
  - public/assets/css/email-templates.css
decisions:
  - help page now uses app-header with page-title + gradient bar (was simple .help-header div)
  - email-templates page upgraded to v4.3 breadcrumb + page-title + page-sub pattern
  - both CSS files converted to 100% token-based (zero hardcoded hex)
  - email-templates toolbar extracted to dedicated .email-templates-toolbar flex row
metrics:
  duration_seconds: 363
  completed_date: "2026-03-30"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 4
---

# Phase 51 Plan 01: Utility Pages Rebuild Summary

Help/FAQ and Email Templates pages rebuilt with v4.3 design language — app-header page-title pattern, gradient accent bar, token-based CSS, and complete DOM selector preservation for both JS files.

## Tasks Completed

| Task | Name | Commit | Files |
| ---- | ---- | ------ | ----- |
| 1 | Rebuild Help/FAQ page (HTML+CSS) | 372cbf4 | public/help.htmx.html, public/assets/css/help.css |
| 2 | Rebuild Email Templates page (HTML+CSS) + verify | e040f0a | public/email-templates.htmx.html, public/assets/css/email-templates.css |

## What Was Built

### Help/FAQ Page (help.htmx.html + help.css)
- Replaced simple `.help-header` div with v4.3 `app-header` containing `page-title` with `.bar` gradient span, help-circle icon, breadcrumb and `page-sub`
- Preserved all 25 FAQ items across 5 sections (general, operator, vote, members, security)
- Preserved all 11 tour cards with correct `data-required-role` attributes
- Pill-shape tabs with solid active state (var(--color-primary) background)
- FAQ accordion uses CSS `max-height: 0` / `500px` transition instead of `display: none` (smoother)
- All `help-faq.js` selectors matched: `faqSearch`, `faqContent`, `.help-tab[data-tab]`, `.faq-section[data-category]`, `.faq-item[data-search]`, `.faq-question span`, `.faq-answer`, `.faq-chevron`
- help.css: 100% token-based, print media query hides tour-grid/tabs/search

### Email Templates Page (email-templates.htmx.html + email-templates.css)
- Added v4.3 `app-header` with back-arrow link, breadcrumb "Administration > Templates Email", `page-title` with mail icon, `page-sub`
- Toolbar extracted to `.email-templates-toolbar` flex row with `#filterType` select + `#btnCreateDefaults` + `#btnNewTemplate`
- All 21 DOM IDs preserved: templatesGrid, emptyState, filterType, templateEditor, previewFrame, variablesList, templateId, templateName, templateType, templateSubject, templateBody, templateIsDefault, editorTitle, editorStatus, btnNewTemplate, btnEmptyCreate, btnCloseEditor, btnCancelEdit, btnSaveTemplate, btnCreateDefaults, btnRefreshPreview
- Editor modal: two-panel layout (form left / preview iframe right), `.active` class toggles `display: none` → `display: flex`
- email-templates.css: 100% token-based, responsive stacks at 768px

## Decisions Made

- Help page uses `app-header` (not in-content header) to match all other rebuilt pages in v4.3/v4.4
- FAQ accordion upgraded from `display: none/block` to `max-height` transition for smooth animation
- Email templates toolbar uses dedicated `.email-templates-toolbar` class instead of ad-hoc utility classes for better maintainability
- Both pages' footers updated to version "v4.4"

## Deviations from Plan

None - plan executed exactly as written. Both JS files untouched.

## Verification

All checks passed:
- 25/25 faq-item elements in help.htmx.html
- 5/5 faq-section elements with data-category
- 6/6 help-tab elements with data-tab
- 15 data-required-role attributes (tour cards + tabs + sections)
- 21/21 email-templates DOM IDs present
- Zero hardcoded hex colors in either CSS file
- Both JS files correctly linked via script tags
