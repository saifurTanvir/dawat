// One day in one zone (North Badda), every role in turn — the flow the QA/UX
// audit walked by hand: a new cook joins and is approved, posts dinner, a
// feed buyer books a plate to pick up, the admin adds an area manager and a
// regular customer, the cook says "ready", the area manager picks up and
// delivers with the SMS code, pays the cook at her door, and the money lands
// in the admin's accounts. Then a guest meal and a question on a sold-out post.
//
// Tests run in order and share state; each role has its own browser context.
import { test, expect } from '@playwright/test';
import { PHONES, acceptDialogs, adminLogin, answerYes, appLogin, expectFilamentNotification, filamentSearchSelect, lastSmsCode, modal } from './helpers.js';

test.describe.configure({ mode: 'serial' });

const COOK_NAME = 'সালমা আক্তার (E2E)';
const KITCHEN = 'সালমা আপার রান্নাঘর (E2E)';
const DISH = 'ভাত, গরুর মাংস, ডাল';
const BUYER_NAME = 'তানভীর (E2E)';
const BUYER_NOTE = 'রাত ৯টায় নিতে আসব';
const AM_NAME = 'করিম (E2E এলাকা ম্যানেজার)';
const REGULAR_NAME = 'রাফি (E2E গ্রাহক)';
const READY_NOTE = 'গেটের দারোয়ানকে বলবেন';

/** @type {Record<string, import('@playwright/test').Page>} */
const as = {};
let postPath = null;

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

test('গৃহিণী: "রান্না করে আয় করুন" পাতা পড়ে যোগ দেন, নিজের নাম আর রান্নাঘরের নাম দেন', async () => {
    const page = as.cook;
    await page.goto('/');
    await page.getByText('রান্না করে আয় করুন').first().click();
    await expect(page.getByText('কত আয়, টাকা কীভাবে')).toBeVisible(); // F112: explains before sign-up
    await page.getByRole('link', { name: 'রাঁধুনি হিসেবে যোগ দিন' }).first().click();
    await expect(page).toHaveURL(/\/login\?next=(%2F|\/)cook/);

    await appLogin(page, PHONES.newCook);
    await expect(page).toHaveURL(/\/cook$/);
    await page.getByPlaceholder('যেমন: সালমা আক্তার').fill(COOK_NAME);
    await page.getByPlaceholder('যেমন: রহিমা খালার রান্নাঘর').fill(KITCHEN);
    await page.getByRole('button', { name: 'দাওয়াতে যোগ দিন' }).click();
    await expect(page.getByText('অনুমোদনের অপেক্ষায়')).toBeVisible();

    // F113: the waiting page says what happens next and asks 1 or 2 plates.
    await expect(page.getByText('অনুমোদনের আগে', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: '১ প্লেট' }).click();
    await expect(page.getByText('জানানো হয়েছে। দাওয়াত টিম ফোনে চূড়ান্ত করবে।')).toBeVisible();

    await page.getByRole('link', { name: 'সেটআপ করুন' }).click();
    await page.getByRole('button', { name: /আমি এখন বাসায় আছি/ }).click();
    await expect(page.locator('select').first()).toHaveValue(/.+/);
    await page.getByRole('button', { name: 'সংরক্ষণ করুন' }).click();
    await expect(page).toHaveURL(/\/cook$/);
    await expect(page.getByText('সেটআপ বাকি আছে')).toHaveCount(0);
});

test('অ্যাডমিন: গৃহিণীর নাম দেখেন; যাচাইয়ের টিক ছাড়া অনুমোদন হয় না, টিক দিলে হয়', async () => {
    const page = as.admin;
    await adminLogin(page);
    await page.goto('/admin/kitchens');
    await page.getByText(KITCHEN).click();
    await expect(page.getByText(COOK_NAME).first()).toBeVisible();
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

test('গৃহিণী: ৪ ধাপে রাতের খাবার পোস্ট করেন, "আপনি পাবেন" দেখেন, WhatsApp-এ জানানোর বোতাম পান', async () => {
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
    await expect(page.getByRole('link', { name: /WhatsApp-এ পরিচিতদের পাঠান/ })).toBeVisible(); // F112
});

test('ক্রেতা: লগইনে কোন খাবার বুক করছেন দেখেন, নাম আর নোট দিয়ে "নিজে নেব" বুক করেন', async () => {
    const page = as.buyer;
    await page.goto('/');
    await page.getByRole('button', { name: 'আমার কাছের খাবার দেখুন' }).click();
    const card = page.locator('article, li, div').filter({ hasText: KITCHEN }).filter({ has: page.getByRole('button', { name: 'নিতে চাই' }) }).last();
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'নিতে চাই' }).click();

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByText(/বুক করতে আপনার মোবাইল নম্বর দিন/)).toBeVisible(); // F112 banner
    await appLogin(page, PHONES.feedBuyer);
    await expect(page).toHaveURL(/\/p\/\d+\?order=1/);
    postPath = new URL(page.url()).pathname;

    await page.getByPlaceholder('গৃহিণী জানবেন কার জন্য রাখছেন').fill(BUYER_NAME);
    await page.getByPlaceholder('যেমন: গেটে এসে ফোন দেবেন').fill(BUYER_NOTE);
    await page.getByRole('button', { name: 'বুক করুন' }).click();
    await expect(page.getByText('বুক হয়েছে')).toBeVisible();

    await page.goto('/orders');
    await expect(page.getByText(DISH)).toBeVisible();
});

test('গৃহিণী: অর্ডারে ক্রেতার নাম আর নোট দেখেন, অর্ডার পাতার উপরের দিকে', async () => {
    const page = as.cook;
    await page.goto('/notifications');
    await expect(page.getByText(`নতুন অর্ডার: ১ প্লেট · ${BUYER_NAME}`)).toBeVisible();
    await expect(page.getByText(BUYER_NOTE).first()).toBeVisible();

    await page.goto('/cook');
    await expect(page.getByText(`ক্রেতা লিখেছেন: “${BUYER_NOTE}”`)).toBeVisible();
    const orders = await page.getByText('আজকের অর্ডার').boundingBox();
    const post = await page.getByRole('link', { name: 'আজকের রান্না পোস্ট করুন' }).boundingBox();
    expect(orders.y).toBeLessThan(post.y);
});

test('গৃহিণী: টাকা পেয়ে "দিয়েছি" দেন — অ্যাপের নিজের প্রশ্নে "হ্যাঁ"', async () => {
    const page = as.cook;
    await page.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await answerYes(page);
    await expect(page.getByText('দেওয়া হয়েছে, লিখে রাখা হলো।')).toBeVisible();
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

test('গৃহিণী: নতুন নিয়মিত গ্রাহকের প্লেটের খবর পান (F45 ফিক্স)', async () => {
    const page = as.cook;
    await page.goto('/notifications');
    await expect(page.getByText(/নতুন নিয়মিত গ্রাহক: আজ রাত ১ প্লেট আলাদা রাখুন/)).toBeVisible();
    await expect(page.getByText(/করিম .* নিতে আসবেন/)).toBeVisible();
});

test('গৃহিণী: "রেডি" জানান — এলাকা ম্যানেজার লগইন করে সোজা আজকের তালিকায় নোটসহ দেখেন', async () => {
    const page = as.cook;
    await page.goto('/cook');
    await page.getByRole('button', { name: /খাবার রেডি — এলাকা ম্যানেজারকে জানান/ }).click();
    await page.locator('#ready-dinner').fill(READY_NOTE);
    await page.getByRole('button', { name: 'জানান', exact: true }).click();
    await expect(page.getByText(new RegExp(`এলাকা ম্যানেজারকে জানানো হয়েছে .*${READY_NOTE}`))).toBeVisible();

    const am = as.am;
    await am.goto('/login');
    await appLogin(am, PHONES.areaManager); // no ?next= — must still land on his list (F112)
    await expect(am).toHaveURL(/\/shahidar$/);
    await expect(am.getByText(new RegExp(`খাবার রেডি .*${READY_NOTE}`))).toBeVisible();
    await expect(am.getByRole('link', { name: /ম্যাপে পথ/ }).first()).toBeVisible(); // F29 fix: map link to the cook
});

test('এলাকা ম্যানেজার: ভুল কোডের এরর বক্সের কার্ডেই; "নগদ পেয়েছি" শুরুতে খালি; সঠিক কোডে ডেলিভারি', async () => {
    const page = as.am;
    await page.getByRole('button', { name: 'পিকআপ', exact: true }).click();
    await page.getByRole('button', { name: 'স্টিকার ছাড়াই পিকআপ' }).click();
    await page.getByRole('button', { name: 'হাতে দিচ্ছি' }).click();

    const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'দিয়েছি', exact: true }) });
    const card = form.locator('xpath=..');
    const cash = card.locator('input[type=checkbox]');
    await expect(cash).not.toBeChecked(); // F27 fix
    const code = form.locator('input:not([type=checkbox]):not([type=hidden])').last();

    await code.fill('0000');
    await form.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await expect(card.getByText(/কোড মেলেনি/)).toBeVisible(); // F27 fix: in the card, not at the top

    const sms = await lastSmsCode(PHONES.regular);
    expect(sms, 'delivery code in the (dummy) SMS log').toMatch(/^\d{4}$/);
    await code.fill(sms);
    await cash.check();
    await form.getByRole('button', { name: 'দিয়েছি', exact: true }).click();
    await expect(page.getByText(/শেষ হয়েছে \(১\)/)).toBeVisible();
});

test('হিসাব: এলাকা ম্যানেজার গৃহিণীকে দরজায় টাকা দেন, গৃহিণী নিশ্চিত করেন, অ্যাডমিনে মেলে', async () => {
    const am = as.am;
    await expect(am.getByText('আজকের হিসাব')).toBeVisible();
    await am.getByRole('button', { name: '৳১৫০ হাতে দিয়েছি' }).click();
    await answerYes(am);
    await expect(am.getByText(/গৃহিণীদের দিয়েছেন\s*৳১৫০/)).toBeVisible();

    const cook = as.cook;
    await cook.goto('/cook');
    await expect(cook.getByText(/এলাকা ম্যানেজার জানিয়েছেন: ৳১৫০ দিয়েছেন/)).toBeVisible();
    await cook.getByRole('button', { name: 'হ্যাঁ, পেয়েছি' }).click();
    await expect(cook.getByText('ধন্যবাদ — লেখা হলো আপনি পেয়েছেন।')).toBeVisible();

    const admin = as.admin;
    await admin.goto('/admin/money');
    const amRow = admin.locator('tr').filter({ hasText: AM_NAME });
    await expect(amRow).toContainText('৳১৬০'); // collected
    await expect(amRow).toContainText('৳১৫০'); // given to cooks
    await expect(amRow).toContainText('৳১০'); // still owed to Dawat
    await expect(admin.getByText('কারো পাওনা বাকি নেই।').first()).toBeVisible();
});

test('নিয়মিত গ্রাহক: পৌঁছানোর পর "কেমন লাগল? তারা দিন" পান', async () => {
    const page = as.regular;
    await page.goto('/login');
    await appLogin(page, PHONES.regular, '/regular');
    await expect(page.getByText(/· পৌঁছেছে/)).toBeVisible();
    await expect(page.getByText('কেমন লাগল? তারা দিন')).toBeVisible();
});

test('অতিথির খাবার: পোস্ট শেষ হলেও গৃহিণী "পারব" বললে বক্স তৈরি হয়, এলাকা ম্যানেজার পিকআপে পান', async () => {
    const page = as.regular;
    await page.getByRole('button', { name: 'অতিথির খাবার চাই' }).click();
    await page.getByPlaceholder('যেমন: ঝাল কম, একজন বাচ্চা').fill('দুইজন মেহমান');
    await page.getByRole('button', { name: 'অনুরোধ পাঠান' }).click();
    await expect(page.getByText('গৃহিণীর উত্তরের অপেক্ষায়')).toBeVisible();

    const cook = as.cook;
    await cook.goto('/cook');
    await expect(cook.getByText('অতিথির জন্য বাড়তি খাবার')).toBeVisible();
    await cook.getByRole('button', { name: 'পারব', exact: true }).click();
    await expect(cook.getByText('ব্যবস্থা হয়েছে').first()).toBeVisible();
    await cook.reload();
    await expect(cook.getByText(/অতিথির খাবার — দুইজন মেহমান/)).toBeVisible(); // F64 fix: a real box

    const am = as.am;
    await am.goto('/shahidar');
    await expect(am.getByText(/রাত · .*২ প্লেট/).first()).toBeVisible();
});

test('ক্রেতা: প্লেট শেষ হওয়া পোস্টেও প্রশ্ন করতে পারেন', async () => {
    const page = as.buyer;
    await page.goto(postPath);
    const box = page.getByPlaceholder(/প্রশ্ন করুন/);
    await expect(box).toBeVisible();
    await box.fill('আপা, কাল কি খিচুড়ি হবে?');
    await page.getByRole('button', { name: 'কমেন্ট পাঠান' }).click();
    await expect(page.getByText('আপা, কাল কি খিচুড়ি হবে?')).toBeVisible();
});

// Known issues from the recheck (27 Nov). Marked test.fail(): they turn red
// ("Expected to fail, but passed") once fixed — then delete the test.fail() line.

test('জানা সমস্যা: "রেডি" জানানোর পরে আসা অতিথির বক্সকে এলাকা ম্যানেজার "রেডি নয়" দেখেন', async () => {
    test.fail();
    const am = as.am;
    await am.goto('/shahidar');
    await expect(am.getByText(/রাত · .*২ প্লেট/).first()).toBeVisible(); // list loaded
    await expect(am.getByText('গৃহিণী এখনো "রেডি" জানাননি।')).toHaveCount(0, { timeout: 3_000 });
});

test('জানা সমস্যা: ক্রেতার কমেন্টের খবর গৃহিণী পান না', async () => {
    test.fail();
    const cook = as.cook;
    await cook.goto('/notifications');
    await expect(cook.getByText('নোটিফিকেশন', { exact: true }).first()).toBeVisible(); // page loaded
    await expect(cook.getByText(/কমেন্ট/).first()).toBeVisible({ timeout: 3_000 });
});
