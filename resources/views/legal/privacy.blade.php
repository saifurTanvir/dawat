{{-- F81: privacy policy (Meta asks for one before the Messenger app can go live). --}}
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1E5B3F">
    @include('partials.meta')
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="mx-auto max-w-2xl px-4 pt-5 pb-12 leading-relaxed">
    <a href="/" class="flex items-center gap-2">
        <img src="/brand/mark.png" alt="" width="63" height="32" class="h-8 w-auto">
        <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="h-8 w-auto">
    </a>

    <h1 class="mt-6 font-serif text-3xl font-bold">গোপনীয়তা নীতি</h1>
    <p class="text-muted">Privacy policy · সর্বশেষ বদল: ১৯ নভেম্বর ২০২৬</p>

    <p class="mt-4">দাওয়াত (dawat.cloud) পাড়ার গৃহিণীদের ঘরে রান্না করা খাবার আশেপাশের মানুষের কাছে পৌঁছে দেয়। এই পাতায় লেখা আছে আমরা আপনার কোন তথ্য রাখি, কেন রাখি, আর কীভাবে মুছে দিতে বলবেন।</p>

    <h2 class="mt-6 text-xl font-semibold">আমরা যা রাখি</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li>নাম আর মোবাইল নম্বর — লগইন, অর্ডার আর যোগাযোগের জন্য।</li>
        <li>এলাকা, ঠিকানা আর বাসার লোকেশন — খাবার পৌঁছাতে আর কাছের গৃহিণী খুঁজতে। গৃহিণীর বাসার লোকেশন কোনো গ্রাহক দেখেন না; শুধু আনুমানিক দূরত্ব দেখানো হয়।</li>
        <li>অর্ডার, পোস্ট, ছবি, কমেন্ট আর টাকার হিসাব — সেবা চালাতে।</li>
        <li>আমাদের Facebook পেজের Messenger-এ আপনি যা লেখেন (আর Facebook-এ আপনার নাম) — আপনার প্রশ্নের উত্তর দিতে আর আপনি চাইলে আপনাকে গৃহিণী হিসেবে যোগ করতে। উত্তর লিখতে আমরা AI (Anthropic-এর Claude) ব্যবহার করি; চ্যাটের লেখা শুধু উত্তর তৈরির জন্য তাদের কাছে যায়।</li>
    </ul>

    <h2 class="mt-6 text-xl font-semibold">যাদের সাথে ভাগ করি</h2>
    <p class="mt-2">আপনার তথ্য আমরা বিক্রি করি না। অর্ডারের জন্য যতটুকু লাগে — গৃহিণী আর ডেলিভারির এলাকা ম্যানেজার গ্রাহকের নাম, নম্বর, ঠিকানা দেখেন। SMS, ম্যাপ, মেসেজ আর AI-এর জন্য যে সেবাগুলো ব্যবহার করি (যেমন Meta, Google, Anthropic, SMS কোম্পানি), তারা শুধু সেই কাজটুকুর জন্য তথ্য পায়।</p>

    <h2 class="mt-6 text-xl font-semibold">কতদিন রাখি</h2>
    <p class="mt-2">অ্যাকাউন্ট চালু থাকা পর্যন্ত। টাকার হিসাব আইন অনুযায়ী যতদিন রাখা দরকার, ততদিন।</p>

    <h2 id="delete" class="mt-6 text-xl font-semibold">তথ্য মুছে ফেলতে চাইলে</h2>
    <p class="mt-2">আমাদের Facebook পেজ "দাওয়াত - পাড়ার ঘরের খাবার"-এর Messenger-এ "আমার তথ্য মুছে দিন" লিখুন, সাথে অ্যাকাউন্টের মোবাইল নম্বর। আমরা ৭ দিনের মধ্যে আপনার অ্যাকাউন্ট, Messenger চ্যাট আর ব্যক্তিগত তথ্য মুছে জানিয়ে দেব (শুধু আইনে যে হিসাব রাখতে হয় তা থাকবে)।</p>

    <h2 class="mt-6 text-xl font-semibold">In English (summary)</h2>
    <p class="mt-2 text-sm">Dawat (dawat.cloud) connects home cooks with nearby customers. We keep your name, phone number, area, address and home location (a cook's location is never shown to customers), your orders and posts, and what you write to our Facebook Page on Messenger together with your Facebook name. We use them only to run the service, answer you and, if you ask, add you as a cook; replies on Messenger are drafted with AI (Anthropic's Claude). We never sell your data; we share it only with the cook, the delivery person and the providers that do the work for us (Meta, Google, Anthropic, our SMS provider). To delete your data, message our Facebook Page "দাওয়াত - পাড়ার ঘরের খাবার" on Messenger with "delete my data" and your phone number; we delete it within 7 days, except records the law requires us to keep.</p>
</main>
</body>
</html>
