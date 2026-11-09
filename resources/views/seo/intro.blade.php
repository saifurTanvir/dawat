<div class="mx-auto max-w-lg">
    <header class="flex items-center gap-2 px-4 pt-5 pb-3">
        <a href="{{ url('/') }}" class="flex items-center gap-2"><img src="/brand/mark.png" alt="" width="63" height="32" class="h-8 w-auto"><img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="h-8 w-auto"></a>
        <span class="text-sm text-muted">পাড়ার ঘরের খাবার</span>
    </header>
    <main class="px-4 pt-2">
        <img src="/brand/lockup.png" alt="দাওয়াত — পাড়ার ঘরের খাবার। পুরো পরিবার এক টেবিলে, পাশের বাসার রান্না" width="800" height="761" class="mx-auto mb-4 w-56">
        <h1 class="font-serif text-2xl font-bold">আজকের দাওয়াত — ঢাকায় পাড়ার ঘরের খাবার</h1>
        <p class="mt-2 text-muted">
            {{ \App\Support\Seo::of(config('dawat.seo.region')) }} গৃহিণীরা নিজের বাসার রান্না থেকে কয়েক প্লেট বিক্রি করেন।
            আজকের দুপুর আর রাতের রান্না দেখুন, অ্যাপেই অর্ডার করুন — শাহিদার বাসায় বা অফিসে পৌঁছে দেন,
            টাকা দেবেন খাবার হাতে পেয়ে। প্রতিদিন দুপুরের লাঞ্চও বুক করা যায়।
        </p>
        @include('seo.footer')
    </main>
</div>
