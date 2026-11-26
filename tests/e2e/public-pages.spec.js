// Pages anyone sees before logging in.
import { test, expect } from '@playwright/test';

test('হোম: চার দরজা আর আজকের খাবার দেখায়', async ({ page }) => {
    await page.goto('/');
    for (const door of ['আজকের খাবার কিনুন', 'রোজ দুপুরের লাঞ্চ নিন', 'রান্না করে আয় করুন', 'এলাকা ম্যানেজার হয়ে আয় করুন']) {
        await expect(page.getByText(door).first()).toBeVisible();
    }
    await expect(page.getByText('আজ যা রান্না হয়েছে')).toBeVisible();
});

test('ফিড: "আমার কাছের খাবার" কাছেরটা আগে, দূরত্বসহ', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'আমার কাছের খাবার দেখুন' }).click();
    await expect(page.getByText(/কিমির মধ্যে খাবার দেখাচ্ছে/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'নিতে চাই' }).first()).toBeVisible();
    await expect(page.getByText(/\d* ?মি · হেঁটে/).first()).toBeVisible();
});

test('লগইন ছাড়া "নিতে চাই" চাপলে লগইনে যায়, তারপর সেই পোস্টে ফেরে', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'আমার কাছের খাবার দেখুন' }).click();
    await page.getByRole('button', { name: 'নিতে চাই' }).first().click();
    await expect(page).toHaveURL(/\/login\?next=.*p.*\d+/);
});

// Known issue (QA audit): the footer says Dawat is live only in Badda, then
// lists North/Middle/South Badda under "যেসব এলাকায় শিগগিরই আসছি" (coming soon).
test('জানা সমস্যা: "শিগগিরই আসছি" তালিকায় চালু বাড্ডার এলাকাগুলোও আছে', async ({ page }) => {
    test.fail();
    await page.goto('/');
    const soon = page.getByText('যেসব এলাকায় শিগগিরই আসছি').locator('xpath=following-sibling::ul[1]');
    await expect(soon.getByRole('link').first()).toBeVisible(); // list loaded
    await expect(soon.getByRole('link', { name: 'উত্তর বাড্ডা', exact: true })).toHaveCount(0, { timeout: 3_000 });
});
