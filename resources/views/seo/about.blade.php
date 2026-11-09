@extends('seo.layout')

@section('content')
    <img src="/brand/mark.png" alt="এক টেবিলে পুরো পরিবার, টেবিলে ঘরের রান্না" width="720" height="364" class="mx-auto mt-4 w-full max-w-sm">
    <h1 class="mt-3 font-serif text-3xl font-bold">দাওয়াত — পাড়ার ঘরের খাবার</h1>
    <p class="mt-1 text-muted">ঘর থেকে দূরে, তবু খাবারটা ঘরের</p>
    <p class="mt-3 text-lg leading-relaxed">
        দাওয়াত ঢাকার {{ \App\Support\Seo::of(config('dawat.seo.region')) }} গৃহিণীদের ঘরে রান্না খাবার পাড়ার মানুষের কাছে পৌঁছে দেয়।
        ফিডে আজকের রান্না দেখে অর্ডার করা যায়, আবার অফিসে বা বাসায় প্রতিদিনের লাঞ্চও নেওয়া যায়।
    </p>

    <section class="mt-8" aria-labelledby="faq">
        <h2 id="faq" class="font-serif text-xl font-bold">প্রশ্ন ও উত্তর</h2>
        <dl class="mt-3 space-y-5 leading-relaxed">
            @foreach ($faq as [$q, $a])
                <div>
                    <dt class="font-semibold">{{ $q }}</dt>
                    <dd class="mt-1">{{ $a }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="mt-8" aria-labelledby="contact">
        <h2 id="contact" class="font-serif text-xl font-bold">যোগাযোগ</h2>
        <p class="mt-2">ফোন: <a href="tel:{{ \App\Support\Seo::phoneIntl() }}" class="font-semibold">{{ \App\Support\Seo::phoneBangla() }}</a></p>
        @if (config('dawat.contact.facebook_url'))
            <p class="mt-1">ফেসবুক: <a href="{{ config('dawat.contact.facebook_url') }}" class="text-leaf" rel="noopener">দাওয়াতের গ্রুপ</a></p>
        @endif
    </section>
@endsection
