// Fresh dummy data before every run: areas, tags, 5 demo kitchens with posts
// (DemoSeeder) and one login per role (TestUsersSeeder). Nothing real.
import { execSync } from 'node:child_process';
import { E2E_ENV } from '../../playwright.config.js';

export default function globalSetup() {
    const env = { ...process.env, ...E2E_ENV };
    const run = (cmd) => execSync(cmd, { env, stdio: 'inherit' });

    run('php artisan migrate:fresh --seed --force');
    run('php artisan db:seed --class=TestUsersSeeder --force');
    run('php artisan db:seed --class=DemoSeeder --force');
}
