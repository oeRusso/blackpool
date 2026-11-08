import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests. Run them with `bin/e2e`, which boots the app on port 8010
 * with a freshly seeded database and runs Playwright inside its official image.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    forbidOnly: Boolean(process.env.CI),
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'e2e-report' }],
    ],
    timeout: 60_000,
    use: {
        testIdAttribute: 'data-test',
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8010',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    outputDir: 'e2e-results',
    projects: [
        { name: 'setup', testMatch: /global\.setup\.ts/ },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        },
    ],
});
