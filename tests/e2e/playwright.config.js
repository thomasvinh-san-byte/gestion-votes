// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * AG-VOTE Playwright E2E Test Configuration
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './specs',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['line'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],

  // Global setup: authenticate once per role to avoid auth_login rate limits
  // when running tests in parallel.
  globalSetup: require.resolve('./setup/auth.setup.js'),

  use: {
    baseURL: process.env.BASE_URL || (process.env.IN_DOCKER ? 'http://app:8080' : 'http://localhost:8080'),
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    // Mobile viewports for vote tablet interface
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      // Tablet viewport using Chromium (WebKit/iPad device not available in this env).
      // Simulates iPad-sized viewport (768x1024) on Chromium for responsive testing.
      name: 'tablet',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 768, height: 1024 },
        userAgent: 'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
      },
    },
  ],

  // Docker stack is expected to already be running at port 8080.
  // Use `docker compose up -d` before running tests.
  webServer: {
    command: 'echo "Docker stack expected at port 8080"',
    url: (process.env.IN_DOCKER ? 'http://app:8080' : 'http://localhost:8080') + '/login.html',
    reuseExistingServer: !process.env.CI,
    cwd: '../../',
  },
});
