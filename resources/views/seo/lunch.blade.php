@extends('seo.layout')

@section('content')
    <h1 class="mt-3 font-serif text-3xl font-bold">প্রতিদিন দুপুরে ঘরের রান্না, অফিসে বা বাসায়</h1>
    <p class="mt-3 text-lg leading-relaxed">
        হোটেলের তেল-মসলা না, পাড়ার একজন গৃহিণীর বাসার রান্না। একবার অনুরোধ করলেই হলো — রোজ জিজ্ঞেস করতে হয় না,
        রান্না হলেই আপনার বক্স বুক হয়ে যায়।
    </p>

    <section class="mt-8" aria-labelledby="how">
        <h2 id="how" class="font-serif text-xl font-bold">কীভাবে চলে</h2>
        <ol class="mt-3 list-decimal space-y-3 pl-5 leading-relaxed">
            <li>অনুরোধ করুন: এলাকা, ঠিকানা, কোন বেলা (দুপুর, রাত বা দুটোই), কয় প্লেট, সপ্তাহের কোন দিন, আর প্রতি প্লেট সর্বোচ্চ কত টাকা।</li>
            <li>দাওয়াত টিম আপনার এলাকার একজন গৃহিণী আর একজন শাহিদার ঠিক করে দেয়। কে রান্না করবেন আর কে পৌঁছে দেবেন, অ্যাপে দেখতে পাবেন।</li>
            <li>প্রতিদিন গৃহিণী রান্না পোস্ট করলেই আপনার বক্স বুক — SMS-এ বক্স নম্বর, দাম আর ৪ অঙ্কের ডেলিভারি কোড আসে।</li>
            <li>শাহিদার বক্স পৌঁছে দেন। খাবার হাতে পেয়ে নগদ টাকা দিন।</li>
        </ol>
    </section>

    <section class="mt-8" aria-labelledby="control">
        <h2 id="control" class="font-serif text-xl font-bold">সব আপনার হাতে</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 leading-relaxed">
            <li>দাম আপনার সীমার বেশি হলে আগে জিজ্ঞেস করা হয়, রাজি হলে তবেই বুক।</li>
            <li>কোনো দিন লাগবে না? পিকআপের আগে অ্যাপে "আজ লাগবে না"।</li>
            <li>ছুটিতে যাচ্ছেন? আগে থেকে কয়েক দিনের বিরতি দিন।</li>
            <li>রান্না ভালো না লাগলে দাওয়াত টিমকে বলুন, অন্য গৃহিণী ঠিক করে দেওয়া হবে।</li>
        </ul>
    </section>

    <section class="mt-8" aria-labelledby="where">
        <h2 id="where" class="font-serif text-xl font-bold">কোন কোন এলাকায়</h2>
        <p class="mt-2 leading-relaxed">{{ $areas->pluck('name')->implode(', ') }}।</p>
    </section>

    <a href="{{ url('/regular') }}" class="btn-primary mt-8">নিয়মিত খাবারের অনুরোধ করুন</a>
    <p class="mt-3 text-center text-sm text-muted">প্রশ্ন থাকলে ফোন করুন: <a href="tel:{{ \App\Support\Seo::phoneIntl() }}" class="font-semibold text-ink">{{ \App\Support\Seo::phoneBangla() }}</a></p>
@endsection
