// @ts-check
const path = require('path');
const fs   = require('fs');
const http = require('http');
const { execSync } = require('child_process');

/**
 * Global auth setup: logs in once per role via the API (not browser form)
 * and saves the PHPSESSID cookie as a minimal storageState JSON file.
 *
 * Strategy:
 *   - POST /api/v1/auth_login.php with JSON credentials
 *   - Extract Set-Cookie PHPSESSID from the response
 *   - Write .auth/{role}.json with just the PHPSESSID cookie
 *
 * This runs BEFORE any test file.  loginAs* helpers in helpers.js inject
 * the saved PHPSESSID into the browser context before each test, eliminating
 * fresh browser logins and staying well under the rate limit (10 / 300 s).
 *
 * Total logins consumed by the suite:
 *   4  (global setup — one per role)
 * + ~4 (tests that explicitly test the login UX flow)
 * = 8  <  10 (limit)  → safe margin of 2.
 */

const BASE_URL = process.env.BASE_URL
  || (process.env.IN_DOCKER ? 'http://app:8080' : 'http://localhost:8080');

// Derive cookie domain from BASE_URL host. Playwright requires the cookie
// domain to exactly match the request host, otherwise the cookie is dropped.
// Phase 8 baseline (08-03-BASELINE.md) discovered that hard-coding 'localhost'
// here breaks all browser-based tests in Docker (host = 'app').
const COOKIE_DOMAIN = (() => {
  try {
    return new URL(BASE_URL).hostname;
  } catch (e) {
    return 'localhost';
  }
})();

const ACCOUNTS = [
  { role: 'operator',  email: 'operator@ag-vote.local',  password: 'Operator2026!'  },
  { role: 'admin',     email: 'admin@ag-vote.local',      password: 'Admin2026!'     },
  { role: 'voter',     email: 'votant@ag-vote.local',     password: 'Votant2026!'    },
  { role: 'president', email: 'president@ag-vote.local',  password: 'President2026!' },
];

const AUTH_DIR = path.join(__dirname, '..', '.auth');

/**
 * POST to auth_login API and return the PHPSESSID from Set-Cookie, or null.
 */
function apiLogin(email, password) {
  return new Promise((resolve, reject) => {
    const url = new URL('/api/v1/auth_login.php', BASE_URL);
    const body = JSON.stringify({ email, password });

    const options = {
      hostname: url.hostname,
      port: url.port || 80,
      path: url.pathname,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
      },
    };

    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        // Extract PHPSESSID from Set-Cookie header
        const setCookie = res.headers['set-cookie'] || [];
        let phpsessid = null;
        for (const c of setCookie) {
          const match = c.match(/PHPSESSID=([^;]+)/);
          if (match) {
            phpsessid = match[1];
            break;
          }
        }

        try {
          const json = JSON.parse(data);
          if (json.ok && phpsessid) {
            resolve(phpsessid);
          } else if (json.error === 'rate_limit_exceeded') {
            console.warn(`[auth-setup] Rate limited! retry_after=${json.retry_after}s`);
            resolve(null);
          } else {
            console.warn(`[auth-setup] Login failed for ${email}: ${json.error || 'unknown'}`);
            resolve(null);
          }
        } catch (e) {
          resolve(null);
        }
      });
    });

    req.on('error', (e) => {
      console.error(`[auth-setup] Request error: ${e.message}`);
      resolve(null);
    });

    req.write(body);
    req.end();
  });
}

/**
 * Clear auth_login rate limit keys in Redis so the test suite has a clean
 * slate of 10 login attempts.  Silently ignored if Redis / Docker not available.
 */
function clearRateLimit() {
  try {
    const keys = execSync(
      'docker exec agvote-redis redis-cli -a "agvote-redis-dev" KEYS "agvote:ratelimit:auth_login:*" 2>/dev/null',
      { timeout: 5000, encoding: 'utf8' }
    ).trim();

    if (keys && !keys.startsWith('Warning') && keys !== '(empty array)') {
      const keyList = keys.split('\n').filter(k => k && !k.startsWith('Warning'));
      for (const key of keyList) {
        execSync(
          `docker exec agvote-redis redis-cli -a "agvote-redis-dev" DEL "${key.trim()}" 2>/dev/null`,
          { timeout: 5000, encoding: 'utf8' }
        );
      }
      console.log(`[auth-setup] Cleared ${keyList.length} rate-limit key(s) for clean test run.`);
    }
  } catch (e) {
    // Non-fatal: Docker or Redis unavailable — tests may hit rate limit naturally
    console.warn(`[auth-setup] Could not clear rate-limit keys: ${e.message}`);
  }
}

module.exports = async function globalSetup() {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }

  // Clear rate-limit counters so setup logins + explicit login tests (8 total)
  // stay safely under the 10/300s limit.
  clearRateLimit();

  for (const account of ACCOUNTS) {
    const authFile = path.join(AUTH_DIR, `${account.role}.json`);

    const phpsessid = await apiLogin(account.email, account.password);

    if (!phpsessid) {
      console.warn(`[auth-setup] Could not get session for ${account.role}. Tests requiring this role will fall back to fresh login.`);

      // Write an empty auth state so helpers.js knows to fall back
      fs.writeFileSync(authFile, JSON.stringify({ cookies: [], origins: [] }, null, 2));
      continue;
    }

    // Write Playwright-compatible storageState with just the session cookie.
    // domain is derived from BASE_URL host so it matches http://app:8080 in Docker
    // and http://localhost:8080 on the host — hard-coding 'localhost' would break
    // all browser-based tests in Docker (Phase 8 baseline, 08-03-BASELINE.md).
    const state = {
      cookies: [
        {
          name:     'PHPSESSID',
          value:    phpsessid,
          domain:   COOKIE_DOMAIN,
          path:     '/',
          expires:  -1,
          httpOnly: true,
          secure:   false,
          sameSite: 'Lax',
        },
      ],
      origins: [],
    };

    fs.writeFileSync(authFile, JSON.stringify(state, null, 2));
    console.log(`[auth-setup] Saved auth state for ${account.role} (session: ${phpsessid.substr(0, 8)}..., domain: ${COOKIE_DOMAIN})`);
  }
};
