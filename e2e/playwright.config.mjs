import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, DEMO_DIR } from './tests/support.mjs';

// The specs share one demo database and change it, so they run one at a time.
export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 5_000 },
    forbidOnly: !!process.env.CI,
    globalSetup: './tests/restore-preference-table.mjs',
    globalTeardown: './tests/restore-preference-table.mjs',
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        // One worker would let a single idle browser connection block every other request.
        command: 'php -S 127.0.0.1:8017 -t public',
        cwd: DEMO_DIR,
        env: { PHP_CLI_SERVER_WORKERS: '4' },
        url: `${BASE_URL}/login`,
        // Locally an already running demo server is reused; CI always starts a fresh one.
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
