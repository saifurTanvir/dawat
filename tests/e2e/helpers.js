import { expect } from '@playwright/test';

/** Dummy numbers used by the tests (never real people). */
export const PHONES = {
    admin: '01900000000',
    newCook: '01711000001',
    feedBuyer: '01722000001',
    areaManager: '01733000001',
    regular: '01744000001',
};

export const ADMIN = { email: 'admin@dawat.test', password: '123456' };

/** Browser dialogs (if any are left) are accepted, like a user tapping "OK". */
export function acceptDialogs(page) {
    page.on('dialog', (d) => d.accept());
}

/** Answer the app's own yes/no question (AskDialog) with its yes button. */
export async function answerYes(page) {
    const dialog = page.getByRole('alertdialog');
    await dialog.waitFor();
    await dialog.getByRole('button').first().click();
    await expect(dialog).toBeHidden();
}

/**
 * App login with phone. A new number opens an account without a code (F57);
 * an existing account asks for the SMS code — any 6 digits work locally.
 */
export async function appLogin(page, phone, next = null) {
    // Without `next`, log in from the login page we were sent to (keeps its ?next=).
    if (next !== null || !new URL(page.url()).pathname.startsWith('/login')) {
        await page.goto(`/login?next=${encodeURIComponent(next ?? '/')}`);
    }
    await page.locator('input[type=tel]').fill(phone);
    await page.getByRole('button', { name: 'এগিয়ে যান' }).click();
    // Either we land on the app (new number) or the code screen shows up (existing account).
    const codeScreen = page.getByRole('heading', { name: 'SMS-এর কোডটি দিন' });
    await Promise.race([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 10_000 }).catch(() => null),
        codeScreen.waitFor({ timeout: 10_000 }).catch(() => null),
    ]);
    if (await codeScreen.isVisible()) {
        await page.getByRole('textbox', { name: 'কোড' }).fill('123456');
        await page.getByRole('button', { name: 'ঢুকুন' }).click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    }
    await page.waitForLoadState('networkidle');
}

export async function adminLogin(page) {
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(ADMIN.email);
    await page.locator('input[type=password]').fill(ADMIN.password);
    await page.locator('button[type=submit]').click();
    await page.waitForURL(/\/admin\/?$/);
}

/** The visible Filament modal whose heading contains `title`. */
export function modal(page, title) {
    return page.locator('.fi-modal-window').filter({ hasText: title }).last();
}

/** Pick an option in a searchable Filament select inside `scope`. */
export async function filamentSearchSelect(page, scope, index, text) {
    await scope.locator('.fi-select-input-btn').nth(index).click();
    await page.keyboard.type(text);
    await page.locator('[role=option]:visible').filter({ hasText: text }).first().click();
}

export async function expectFilamentNotification(page, text) {
    await expect(page.locator('.fi-no-notification').filter({ hasText: text }).first()).toBeVisible();
}

/** The 4-digit delivery code in the latest booking SMS the log driver wrote for a phone (dummy SMS only). */
export async function lastSmsCode(phone) {
    const { readFileSync } = await import('node:fs');
    const log = readFileSync('storage/logs/laravel.log', 'utf8').split('\n').reverse();
    const line = log.find((l) => l.includes(` ${phone}: `) && /কোড বলবেন: \d{4}/.test(l));
    return line?.match(/কোড বলবেন: (\d{4})/)?.[1] ?? null;
}
