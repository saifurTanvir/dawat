@extends('seo.layout')

@section('content')
    <h1 class="mt-3 font-serif text-3xl font-bold">বাসার রান্না থেকে আয় করুন</h1>
    <p class="mt-3 text-lg leading-relaxed">
        পরিবারের জন্য যা রান্না করেন, তার থেকে কয়েক প্লেট পাড়ার মানুষের কাছে বিক্রি করুন।
        দোকান লাগে না, ডেলিভারি করেন দাওয়াতের এলাকা ম্যানেজার। ঘরে বসেই আয়।
    </p>
    <p class="mt-4 rounded-[var(--radius-plate)] bg-plate p-3">{{ config('dawat.launch.notice') }}</p>

    <section class="mt-8" aria-labelledby="steps">
        <h2 id="steps" class="font-serif text-xl font-bold">কীভাবে শুরু করবেন</h2>
        <ol class="mt-3 list-decimal space-y-3 pl-5 leading-relaxed">
            <li>মোবাইল নম্বর দিয়ে লগইন করুন — পাসওয়ার্ড লাগে না, SMS-এ কোড আসে। রান্নাঘরের একটা নাম দিন।</li>
            <li>দাওয়াত টিম ফোনে কথা বলে অনুমোদন দেবে।</li>
            <li>বাসায় থেকে একবার লোকেশন দিন, যাতে কাছের ক্রেতারা আপনার রান্না আগে দেখেন।</li>
            <li>রান্না হলে ছবি, দাম আর কয় প্লেট দিয়ে পোস্ট করুন। প্লেট ফুরোলে বা সময় পেরোলে পোস্ট নিজেই বন্ধ হয়।</li>
        </ol>
    </section>

    <section class="mt-8" aria-labelledby="money">
        <h2 id="money" class="font-serif text-xl font-bold">কত আয়, টাকা কীভাবে</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 leading-relaxed">
            <li>প্লেটের দাম আপনি ঠিক করেন। দাম লিখলেই অ্যাপ দেখায় আপনি কত পাবেন।</li>
            <li>প্রতি প্লেট থেকে কাটা যায়: {{ collect(app(\App\Services\Money\CookFees::class)->rules(null))->map(fn ($r, $part) => \App\Services\Money\CookFees::PART_LABELS[$part].' '.\App\Services\Money\CookFees::ruleText($r))->implode(', ') }}। ক্রেতা নিজে এসে নিলে কিছু কাটা হয় না।</li>
            <li>টাকা এলাকা ম্যানেজার বাসায় এসে নগদে দেন — প্রতিদিন, বা আপনার পছন্দের বারে সপ্তাহে একবার।</li>
            <li>দিনে ১–২ প্লেট দিয়েও শুরু করা যায়।</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="safe">
        <h2 id="safe" class="font-serif text-xl font-bold">আপনার গোপনীয়তা</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 leading-relaxed">
            <li>আপনার ঠিকানা বা লোকেশন কোনো ক্রেতা দেখেন না — শুধু আনুমানিক দূরত্ব।</li>
            <li>ক্রেতা কীভাবে যোগাযোগ করবেন, আপনি ঠিক করেন: অ্যাপে অর্ডার, হোয়াটসঅ্যাপ, কমেন্ট বা কল।</li>
            <li>চাইলে নিয়মিত গ্রাহকও পাবেন — রোজ দুপুর বা রাতের বাঁধা অর্ডার।</li>
        </ul>
    </section>

    <a href="{{ url('/cook') }}" class="btn-primary mt-8">রাঁধুনি হিসেবে যোগ দিন</a>
    <p class="mt-3 text-center text-sm text-muted">জানতে চাইলে ফোন করুন: <a href="tel:{{ \App\Support\Seo::phoneIntl() }}" class="font-semibold text-ink">{{ \App\Support\Seo::phoneBangla() }}</a></p>
@endsection
