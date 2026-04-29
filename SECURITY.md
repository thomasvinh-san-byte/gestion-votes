# Security policy

AG-Vote is a vote-management platform for associations and collectives.
Security incidents put the integrity of votes — and the trust placed in
the platform — at risk. This document describes the supported versions,
the disclosure process, and the operational expectations for deployers.

## Reporting a vulnerability

**Please do NOT open a public GitHub issue for security problems.**

Send an email to **`security@<your-instance-domain>`** (or, if you don't
operate your own deployment, to the project maintainer listed in the
top-level repository on github.com). Include:

- A clear description of the issue and its impact.
- A minimal proof-of-concept (curl command, request body, screenshot, or
  short script). Avoid attaching credentials or live tokens — redact
  or rotate before sending.
- The version / commit SHA the vulnerability reproduces against.
- Whether you have already disclosed (or plan to disclose) elsewhere.

We will acknowledge receipt within **5 working days**, and aim to have a
remediation merged or a workaround published within **30 days** for
high-severity issues. Coordinated disclosure timelines are negotiable
based on impact and complexity.

## Scope

In scope (security report welcome):

- Vote integrity: double-vote, vote tampering, denial of vote.
- Tenant isolation: cross-tenant data access, privilege escalation.
- Authentication & sessions: brute force, hijack, fixation.
- CSRF / XSS / SSRF / template injection.
- Privilege escalation between roles (operator/auditor/admin/voter).
- File-handling vulnerabilities: PDF magic bytes, formula injection,
  dompdf hardening, path traversal.

Out of scope (please do not file):

- Self-XSS in pages that require pasting code into the browser console.
- Social-engineering / phishing of operators outside the application.
- Vulnerabilities in third-party dependencies that have a published CVE
  but no available patch (open an issue if a patch exists and we haven't
  pulled it in yet).
- Volumetric DDoS — handle at the network edge.

## Supported versions

| Branch          | Status              | Security fixes |
|-----------------|---------------------|----------------|
| `main`          | Active development  | ✓ |
| Latest tagged release | Latest minor  | ✓ |
| Previous minor  | Best-effort         | High-severity only |
| Older releases  | End of life         | None — please upgrade |

## Hardening references

The current security posture is documented in
[`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md). Each finding (F01 to F22) has
a paragraph describing the vector, the mitigation in place, and the
commit/PR that introduced the mitigation.

Production deployments should also follow
[`PRODUCTION.md`](./PRODUCTION.md), specifically the env-var checklist:

- `APP_ENV=production`, `APP_DEBUG=0`
- `APP_SECRET` ≥ 32 random bytes (the boot will refuse otherwise)
- `TRUSTED_PROXIES` set to the IPs of your TLS-terminating proxies
- `MONITOR_WEBHOOK_ALLOWED_HOSTS` set if alerts are enabled
- `EMAIL_REDIRECT_ALLOWED_HOSTS` set if email tracking is enabled
- `SESSION_COOKIE_SAMESITE=Strict` (default) unless cross-site flows
  are required
- `CSP_STRICT_MODE=1` after validating Report-Only on real traffic

## Operational signals

The following structured log prefixes indicate security-relevant events
and should be monitored:

| Prefix                       | Meaning                                      |
|------------------------------|----------------------------------------------|
| `AUTH_FAILURE`               | Failed login attempt                          |
| `SESSION_EXPIRED`            | Session timed out                             |
| `SESSION_REVOKED`            | Session invalidated mid-flight (user disabled / role changed) |
| `RATE_LIMIT`                 | Per-IP rate-limit gate triggered              |
| `EMAIL_TRACKING_RATE_LIMIT`  | Tracking endpoint throttled                   |
| `MONITOR_WEBHOOK_REJECTED`   | Outbound webhook URL refused by UrlValidator  |
| `EMAIL_REDIRECT_REJECTED`    | Email click target refused by UrlValidator    |
| `INVITATION_LEGACY_TOKEN_LOOKUP` | A legacy plaintext invitation token is still being consumed (drain to zero) |
| `SECURITY_ALERT`             | Aggregated escalation: too many of the above for one client |

A simple alert recipe: page when `SECURITY_ALERT` count > 0 over any
5 min window.

## Acknowledgements

Researchers who responsibly disclose security issues will be credited in
the project's release notes (with consent), unless they request anonymity.
