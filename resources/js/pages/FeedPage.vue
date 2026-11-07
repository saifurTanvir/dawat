<template>
    <section aria-labelledby="feed-title" class="pt-4">
        <h1 id="feed-title" class="font-serif text-2xl font-bold">আজকের দাওয়াত</h1>
        <p class="mt-1 text-muted">আশেপাশের গৃহিণীদের আজকের রান্না, কাছেরটা আগে।</p>

        <div class="mt-8 rounded-[var(--radius-plate)] border border-dashed border-rule bg-plate p-6">
            <p class="font-semibold">এখনো কেউ আজকের রান্না পোস্ট করেননি।</p>
            <p class="mt-1 text-muted">দুপুরের আগে আবার দেখুন। নতুন পোস্ট এলে এখানেই দেখাবে।</p>
        </div>

        <aside class="mt-10 border-t border-rule pt-6">
            <p class="font-semibold">বাসায় রান্না করেন?</p>
            <p class="mt-1 text-muted">পরিবারের রান্না থেকে এক-দুই প্লেট পাড়ার মানুষের কাছে বিক্রি করুন।</p>
            <RouterLink to="/cook" class="btn-quiet -ml-4 mt-1">দাওয়াতে রাঁধুনি হিসেবে যোগ দিন</RouterLink>
        </aside>
    </section>
</template>
