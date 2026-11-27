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

// Fixed after the QA audit (F112): live areas and coming-soon areas are separate lists.
test('ফুটার: উত্তর বাড্ডা "এখন চালু"-তে, "শিগগিরই আসছি"-তে নয়', async ({ page }) => {
    await page.goto('/');
    const live = page.getByText('এখন চালু:', { exact: true }).locator('xpath=following-sibling::ul[1]');
    await expect(live.getByRole('link', { name: 'উত্তর বাড্ডা', exact: true })).toBeVisible();
    const soon = page.locator('details').filter({ hasText: 'যেসব এলাকায় শিগগিরই আসছি' });
    await expect(soon.locator('a').first()).toBeAttached(); // list loaded (folded)
    await expect(soon.locator('a', { hasText: /^উত্তর বাড্ডা$/ })).toHaveCount(0);
});
