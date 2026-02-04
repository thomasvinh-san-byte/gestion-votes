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
  reporter: 'html',

  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
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
      name: 'tablet',
      use: { ...devices['iPad (gen 7)'] },
    },
  ],

  // Local dev server
  webServer: {
    command: 'php -S localhost:8000 -t public',
    url: 'http://localhost:8000',
    reuseExistingServer: !process.env.CI,
    cwd: '../../',
  },
});
