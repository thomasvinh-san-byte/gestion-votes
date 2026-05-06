---
phase: 4
phase_name: Uploads & contenu
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
---

# Phase 4 SUMMARY — Uploads & contenu

| Req | Verdict | Approach | Tests |
|---|---|---|---|
| HARDEN-F14 | ✓ | Magic bytes %PDF- before finfo; `Content-Disposition: attachment` (was `inline`); basename BEFORE preg_replace | n/a (covered by upload integration) |
| HARDEN-F15 | ✓ | Sanitize prefix `'` (was `\t`), added `\t` and `\r` to leading-char list, XLSX path also sanitized via sanitizeCsvCell | 10/10 ✓ |
| HARDEN-F16 | ✓ | dompdf: setIsPhpEnabled(false), setChroot([Templates]); confirmed isRemoteEnabled=false | n/a (config-level) |

## Single commit (3 findings logically tight, 5 files)

`feat(F14+F15+F16): upload magic bytes, formula injection, dompdf hardening`
