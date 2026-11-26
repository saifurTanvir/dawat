// E2E tests (QA/UX audit). They drive the real app in a phone-sized Chrome.
//
//   npm run build            # once, the app serves public/build
//   npx playwright install chromium   # once (or set E2E_CHROME=/path/to/chrome)
//   npm run test:e2e
//
// The tests use the `dawat_test` MySQL database (the same one phpunit uses) and
// wipe it with migrate:fresh before each run — never point them at real data.
// Everything is dummy: OTP accepts any code (APP_ENV=local), SMS go to the log.
import { defineConfig, devices } from '@playwright/test';

const PORT = Number(process.env.E2E_PORT || 8010);

export const E2E_ENV = {
    APP_ENV: 'local',
    APP_URL: `http://127.0.0.1:${PORT}`,
    DB_DATABASE: process.env.E2E_DB || 'dawat_test',
    OTP_DRIVER: 'log',
    OTP_ANY_CODE: 'true',
    QUEUE_CONNECTION: 'sync',
    AI_DRIVER: 'none',
    SHARE_CARDS_ENABLED: 'false',
};

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        ...devices['Pixel 7'],
        baseURL: `http://127.0.0.1:${PORT}`,
        locale: 'bn-BD',
        timezoneId: 'Asia/Dhaka',
        // North Badda, next to the seeded kitchens.
        geolocation: { latitude: 23.7872, longitude: 90.4268 },
        permissions: ['geolocation'],
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        launchOptions: process.env.E2E_CHROME ? { executablePath: process.env.E2E_CHROME } : {},
    },
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        env: E2E_ENV,
    },
});
