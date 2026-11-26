// One day in one zone (North Badda), every role in turn — the flow the QA/UX
// audit walked by hand: a new cook joins and is approved, posts dinner, a
// feed buyer books a plate to pick up, the admin adds an area manager and a
// regular customer, the cook says "ready", the area manager picks up and
// delivers with the SMS code, and the money lands in the admin's accounts.
//
// Tests run in order and share state; each role has its own browser context.
import { test, expect } from '@playwright/test';
import { PHONES, acceptDialogs, adminLogin, appLogin, expectFilamentNotification, filamentSearchSelect, lastSmsCode, modal } from './helpers.js';

test.describe.configure({ mode: 'serial' });

const KITCHEN = 'সালমা আপার রান্নাঘর (E2E)';
const DISH = 'ভাত, গরুর মাংস, ডাল';
const AM_NAME = 'করিম (E2E এলাকা ম্যানেজার)';
const REGULAR_NAME = 'রাফি (E2E গ্রাহক)';

/** @type {Record<string, import('@playwright/test').Page>} */
const as = {};

test.beforeAll(async ({ browser }) => {
    for (const role of ['cook', 'admin', 'buyer', 'am', 'regular']) {
        const ctx = await browser.newContext();
        as[role] = await ctx.newPage();
        acceptDialogs(as[role]);
    }
});

test.afterAll(async () => {
    for (const page of Object.values(as)) await page.context().close();
});

test('গৃহিণী: নতুন নম্বরে যোগ দেন, রান্নাঘরের নাম আর বাসার লোকেশন দেন', async () => {
    const page = as.cook;
    await page.goto('/');
    await page.getByText('রান্না করে আয় করুন').first().click();
    await expect(page).toHaveURL(/\/login\?next=%2Fcook|\/login\?next=\/cook/);

    await appLogin(page, PHONES.newCook, '/cook');
    await expect(page).toHaveURL(/\/cook$/);

    await page.getByPlaceholder('যেমন: রহিমা খালার রান্নাঘর').fill(KITCHEN);
    await page.getByRole('button', { name: 'দাওয়াতে যোগ দিন' }).click();
    await expect(page.getByText('অনুমোদনের অপেক্ষায়')).toBeVisible();
    await expect(page.getByText('সেটআপ বাকি আছে')).toBeVisible();

    await page.getByRole('link', { name: 'সেটআপ করুন' }).click();
    await page.getByRole('button', { name: /আমি এখন বাসায় আছি/ }).click();
    // The nearest area is picked from the pin.
    await expect(page.locator('select').first()).toHaveValue(/.+/);
    await page.getByRole('button', { name: 'সংরক্ষণ করুন' }).click();
    await expect(page).toHaveURL(/\/cook$/);
    await expect(page.getByText('সেটআপ বাকি আছে')).toHaveCount(0);
});

test('অ্যাডমিন: নম্বর যাচাইয়ের টিক ছাড়া অনুমোদন হয় না, টিক দিলে হয়', async () => {
    const page = as.admin;
    await adminLogin(page);
    await page.goto('/admin/kitchens');
    await page.getByText(KITCHEN).click();
    await expect(page.getByText('নম্বর যাচাই হয়নি')).toBeVisible();

    await page.getByRole('button', { name: 'অনুমোদন দিন' }).click();
    const m = modal(page, 'অনুমোদনের আগে');
    await m.getByRole('button', { name: 'নিশ্চিত করুন' }).click();
    await expect(m).toBeVisible(); // blocked without the "I called and verified" tick

    await m.locator('input[type=checkbox]').check();
    await m.getByRole('button', { name: 'নিশ্চিত করুন' }).click();
    await page.reload();
    await expect(page.getByText('অনুমোদিত', { exact: true }).first()).toBeVisible();
});

test('গৃহিণী: ৪ ধাপে রাতের খাবার পোস্ট করেন, "আপনি পাবেন" দেখেন', async () => {
    const page = as.cook;
    await page.goto('/cook');
    await expect(page.getByText('অনুমোদন হয়ে গেছে')).toBeVisible();

    await page.getByRole('link', { name: 'আজকের রান্না পোস্ট করুন' }).click();
    await page.locator('input[type=file]').last().setInputFiles('bhat.jpg');
    await page.getByRole('button', { name: 'পরের ধাপ' }).click();

    await page.getByPlaceholder('যেমন: ভাত, মুরগির ঝোল, ডাল').fill(DISH);
    await page.locator('input[type=number]').fill('160');
    await expect(page.getByText(/আপনি পাবেন ৳/)).toBeVisible(); // F56
    await page.getByText('রাত', { exact: true }).first().click();
    await page.getByRole('button', { name: 'পরের ধাপ' }).click();

    await page.getByText('হালাল', { exact: true }).click();
    await page.getByRole('button', { name: 'পরের ধাপ' }).click();

    await expect(page.getByText('ক্রেতারা এভাবে দেখবেন')).toBeVisible();
    await page.getByRole('button', { name: 'পোস্ট করুন' }).click();
    await expect(page.getByText('পোস্ট হয়ে গেছে')).toBeVisible();
});

test('ক্রেতা: ফিডে কাছের খাবার দেখেন, নতুন নম্বরে ঢুকে "নিজে নেব" বুক করেন', async () => {
    const page = as.buyer;
    await page.goto('/');
    await page.getByRole('button', { name: 'আমার কাছের খাবার দেখুন' }).click();
    const card = page.locator('article, li, div').filter({ hasText: KITCHEN }).filter({ has: page.getByRole('button', { name: 'নিতে চাই' }) }).last();
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'নিতে চাই' }).click();

    await expect(page).toHaveURL(/\/login/);
    await appLogin(page, PHONES.feedBuyer);
    await expect(page).toHaveURL(/\/p\/\d+\?order=1/);

    await page.getByPlaceholder('যেমন: গেটে এসে ফোন দেবেন').fill('রাত ৯টায় নিতে আসব');
    await page.getByRole('button', { name: 'বুক করুন' }).click();
    await expect(page.getByText('বুক হয়েছে')).toBeVisible();

    await page.goto('/orders');
    await expect(page.getByText(DISH)).toBeVisible();
});

test('গৃহিণী: নতুন অর্ডারের নোটিফিকেশন পান, টাকা পেয়ে "দিয়েছি" দেন', async () => {
    const page = as.cook;
    await page.goto('/notifications');
    await expect(page.getByText(/নতুন অর্ডার: ১ প্লেট/)).toBeVisible();

    await page.goto('/cook');
    await expect(page.getByRole('link', { name: 'ক্রেতাকে ফোন' })).toBeVisible();
    await page.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await expect(page.getByText('দেওয়া হয়েছে, লিখে রাখা হলো।')).toBeVisible();
});

// Known issue (QA audit): the buyer's note ("রাত ৯টায় নিতে আসব") is saved but
// only the area manager's API sends it — for a pickup order the cook never
// sees it. Marked test.fail() so it turns red (unexpected pass) once fixed.
test('জানা সমস্যা: "নিজে নেব" অর্ডারে ক্রেতার নোট গৃহিণী দেখেন না', async () => {
    test.fail();
    await as.cook.goto('/cook');
    await expect(as.cook.getByText('রাত ৯টায় নিতে আসব')).toBeVisible({ timeout: 3_000 });
});

test('অ্যাডমিন: এলাকা ম্যানেজার আর নিয়মিত গ্রাহক যোগ করে নতুন গৃহিণীর সাথে জুটি বাঁধেন', async () => {
    test.slow(); // three Filament forms
    const page = as.admin;
    await page.goto('/admin');

    await page.getByRole('button', { name: 'এলাকা ম্যানেজার যোগ করুন' }).click();
    let m = modal(page, 'নতুন এলাকা ম্যানেজার');
    await m.locator('input[id$=".phone"]').fill(PHONES.areaManager);
    await m.locator('input[id$=".name"]').fill(AM_NAME);
    await filamentSearchSelect(page, m, 0, 'উত্তর বাড্ডা');
    await page.keyboard.press('Escape'); // close the dropdown
    await m.getByRole('button', { name: 'যোগ করুন' }).click();
    await expectFilamentNotification(page, 'এলাকা ম্যানেজার যোগ হয়েছে');

    await page.goto('/admin');
    await page.getByRole('button', { name: 'গ্রাহক যোগ করুন' }).click();
    m = modal(page, 'নতুন নিয়মিত গ্রাহক');
    await m.locator('input[id$=".phone"]').fill(PHONES.regular);
    await m.locator('input[id$=".name"]').fill(REGULAR_NAME);
    await filamentSearchSelect(page, m, 0, 'উত্তর বাড্ডা');
    await m.getByText('ডেলিভারির ঠিকানা').first().click();
    await m.locator('textarea[id$=".address"]').fill('বাসা ১২, রোড ৩, উত্তর বাড্ডা (E2E)');
    await m.getByLabel('রাত', { exact: true }).evaluate((e) => e.click());
    await m.locator('input[id$=".plates"]').fill('1');
    for (const day of ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি']) {
        const box = m.getByLabel(day, { exact: true });
        if (!(await box.isChecked())) await box.evaluate((e) => e.click());
    }
    await m.getByRole('button', { name: 'যোগ করুন' }).click();
    await expectFilamentNotification(page, 'গ্রাহক যোগ হয়েছে');
    await expect(page).toHaveURL(/\/admin\/customers\/\d+/);

    await page.getByRole('button', { name: 'জুটি বাঁধুন' }).click();
    m = modal(page, 'জুটি বাঁধুন');
    await filamentSearchSelect(page, m, 0, 'সালমা');
    const am = m.locator('select');
    await am.selectOption(await am.locator('option').filter({ hasText: 'করিম' }).getAttribute('value'));
    await m.getByRole('button', { name: 'জমা দিন' }).click();
    await expectFilamentNotification(page, 'জুটি বাঁধা হয়েছে');
});

test('গৃহিণী: "রেডি" জানান — এলাকা ম্যানেজার নোট সহ নোটিফিকেশন পান', async () => {
    const page = as.cook;
    await page.goto('/cook');
    await page.getByRole('button', { name: /খাবার রেডি — এলাকা ম্যানেজারকে জানান/ }).click();
    await page.locator('input:visible, textarea:visible').last().fill('গেটের দারোয়ানকে বলবেন');
    await page.getByRole('button', { name: 'জানান', exact: true }).click();
    await expect(page.getByText(/এলাকা ম্যানেজারকে জানানো হয়েছে/).first()).toBeVisible();

    const am = as.am;
    await appLogin(am, PHONES.areaManager, '/shahidar');
    await expect(am.getByText('আজকের তালিকা')).toBeVisible();
    await expect(am.getByText(/খাবার রেডি .*গেটের দারোয়ানকে বলবেন/)).toBeVisible();
});

test('এলাকা ম্যানেজার: পিকআপ, ভুল কোডে আটকায়, সঠিক কোড আর নগদে ডেলিভারি', async () => {
    const page = as.am;
    await page.getByRole('button', { name: 'পিকআপ', exact: true }).click();
    await page.getByRole('button', { name: 'স্টিকার ছাড়াই পিকআপ' }).click();
    await expect(page.getByRole('button', { name: 'হাতে দিচ্ছি' })).toBeVisible();

    await page.getByRole('button', { name: 'হাতে দিচ্ছি' }).click();
    const code = page.locator('form input:not([type=checkbox]):not([type=hidden])').last();

    await code.fill('0000');
    await page.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await expect(page.getByText(/কোড মেলেনি/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'দিয়েছি', exact: true })).toBeVisible(); // still not delivered

    const sms = await lastSmsCode(PHONES.regular);
    expect(sms, 'delivery code in the (dummy) SMS log').toMatch(/^\d{4}$/);
    await code.fill(sms);
    await page.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await expect(page.getByText(/শেষ হয়েছে \(১\)/)).toBeVisible();
    await expect(page.getByText(/হাতে জমা টাকা ৳১৬০/)).toBeVisible();
});

test('নিয়মিত গ্রাহক: আজকের বক্স "পৌঁছেছে" দেখেন', async () => {
    const page = as.regular;
    await appLogin(page, PHONES.regular, '/regular');
    await expect(page.getByText('প্রতিদিন ঘরের খাবার')).toBeVisible();
    await expect(page.getByText(/· পৌঁছেছে/)).toBeVisible();
    await expect(page.getByText(AM_NAME)).toBeVisible();
});

test('হিসাব: অ্যাডমিনের "টাকার হিসাব"-এ এলাকা ম্যানেজারের নগদ আর গৃহিণীর পাওনা মেলে', async () => {
    const page = as.admin;
    await page.goto('/admin/money');
    const amRow = page.locator('tr').filter({ hasText: AM_NAME });
    await expect(amRow).toContainText('160');
    const cookRow = page.locator('tr').filter({ hasText: KITCHEN });
    await expect(cookRow).toContainText('150'); // ৳160 − ৳10 fee; the pickup plate was paid to the cook directly

    const cook = as.cook;
    await cook.goto('/cook/sales');
    await expect(cook.getByText('৳১৫০').first()).toBeVisible();
});

test('অতিথির খাবার: গ্রাহক চান → গৃহিণী "পারব" → এলাকা ম্যানেজার দেখেন', async () => {
    const page = as.regular;
    await page.goto('/regular');
    await page.getByRole('button', { name: 'অতিথির খাবার চাই' }).click();
    await page.getByRole('button', { name: 'কাল', exact: true }).click();
    await page.getByPlaceholder('যেমন: ঝাল কম, একজন বাচ্চা').fill('দুইজন মেহমান');
    await page.getByRole('button', { name: 'অনুরোধ পাঠান' }).click();
    await expect(page.getByText('গৃহিণীর উত্তরের অপেক্ষায়')).toBeVisible();

    const cook = as.cook;
    await cook.goto('/cook');
    await expect(cook.getByText('অতিথির জন্য বাড়তি খাবার')).toBeVisible();
    await cook.getByRole('button', { name: 'পারব', exact: true }).click();
    await expect(cook.getByText('আপনি পারবেন বলেছেন')).toBeVisible();

    const am = as.am;
    await am.goto('/shahidar');
    await expect(am.getByText(/অতিথির খাবার \(১টা বাকি\)/)).toBeVisible();
});
