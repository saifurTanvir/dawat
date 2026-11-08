@extends('seo.layout')

@section('content')
    <h1 class="mt-3 font-serif text-3xl font-bold">{{ \App\Support\Seo::in($area->name) }} ঘরের খাবার</h1>
    <p class="mt-3 text-lg leading-relaxed">
        {{ $area->name }} আর আশেপাশের গৃহিণীরা নিজের বাসার রান্না থেকে কয়েক প্লেট বিক্রি করেন।
        দাওয়াতে আজকের রান্না দেখে "নিতে চাই" চাপলেই অর্ডার — শাহিদার পৌঁছে দেন, টাকা দেবেন খাবার হাতে পেয়ে।
    </p>

    <section class="mt-8" aria-labelledby="today">
        <h2 id="today" class="font-serif text-xl font-bold">{{ \App\Support\Seo::in($area->name) }} আজকের রান্না</h2>
        @if ($posts->isEmpty())
            <p class="mt-2 text-muted">এই মুহূর্তে {{ \App\Support\Seo::in($area->name) }} কোনো রান্না পোস্ট নেই। গৃহিণীরা রান্না শেষ হলে পোস্ট দেন — একটু পরে আবার দেখুন।</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($posts as $post)
                    <li class="rounded-[var(--radius-plate)] bg-plate p-4">
                        <a href="{{ url('/p/'.$post->id) }}" class="font-semibold text-ink underline-offset-2 hover:underline">{{ $post->title }}</a>
                        <p class="mt-1 text-sm text-muted">
                            ৳{{ \App\Models\Order::banglaDigits((int) round($post->price_paisa / 100)) }} প্লেট
                            · {{ $post->meal_slot->label() }}
                            · {{ $post->kitchen->display_name }}
                            · {{ \App\Models\Order::banglaDigits($post->plates_left) }} প্লেট বাকি
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
        <a href="{{ url('/').'/?area='.$area->slug }}" class="btn-primary mt-5">{{ \App\Support\Seo::of($area->name) }} আজকের খাবার দেখুন</a>
    </section>

    <section class="mt-10" aria-labelledby="daily">
        <h2 id="daily" class="font-serif text-xl font-bold">{{ \App\Support\Seo::in($area->name) }} প্রতিদিন দুপুরের খাবার</h2>
        <p class="mt-2 leading-relaxed">
            অফিসে বা বাসায় রোজ ঘরের রান্না চাইলে একবার অনুরোধ করুন। দাওয়াত টিম পাড়ার একজন গৃহিণী আর একজন শাহিদার ঠিক করে দেবে,
            তারপর প্রতিদিন নিজে থেকেই বুক হবে।
            @if ($area->delivery_fee_paisa > 0)
                ডেলিভারি চার্জ ৳{{ \App\Models\Order::banglaDigits(intdiv($area->delivery_fee_paisa, 100)) }}।
            @endif
        </p>
        <a href="{{ url('/lunch') }}" class="btn-quiet -ml-4 mt-1">প্রতিদিনের লাঞ্চ কীভাবে চলে</a>
    </section>

    <section class="mt-8" aria-labelledby="cook">
        <h2 id="cook" class="font-serif text-xl font-bold">{{ \App\Support\Seo::in($area->name) }} বাসায় রান্না করেন?</h2>
        <p class="mt-2 leading-relaxed">পরিবারের রান্না থেকে এক-দুই প্লেট পাড়ার মানুষের কাছে বিক্রি করুন। আপনার ঠিকানা কেউ দেখেন না।</p>
        <a href="{{ url('/rannaghor') }}" class="btn-quiet -ml-4 mt-1">গৃহিণীদের জন্য দাওয়াত</a>
    </section>

    @if ($nearby->isNotEmpty())
        <section class="mt-8" aria-labelledby="nearby">
            <h2 id="nearby" class="font-serif text-xl font-bold">কাছের এলাকা</h2>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                @foreach ($nearby as $n)
                    <li><a href="{{ url('/area/'.$n->slug) }}" class="text-leaf underline-offset-2 hover:underline">{{ \App\Support\Seo::in($n->name) }} ঘরের খাবার</a></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
