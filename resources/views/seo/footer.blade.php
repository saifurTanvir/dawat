{{-- F31: links that let search engines (and people) reach every public page. --}}
<footer class="mt-14 border-t border-rule pt-6 pb-10 text-sm text-muted">
    <p class="font-semibold text-ink">যেসব এলাকায় দাওয়াত আছে</p>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
        @foreach ($areas as $a)
            <li><a href="{{ url('/area/'.$a->slug) }}" class="text-leaf underline-offset-2 hover:underline">{{ $a->name }}</a></li>
        @endforeach
    </ul>
    <ul class="mt-5 flex flex-wrap gap-x-4 gap-y-1">
        <li><a href="{{ url('/') }}" class="text-leaf underline-offset-2 hover:underline">আজকের খাবার</a></li>
        <li><a href="{{ url('/lunch') }}" class="text-leaf underline-offset-2 hover:underline">প্রতিদিনের লাঞ্চ</a></li>
        <li><a href="{{ url('/rannaghor') }}" class="text-leaf underline-offset-2 hover:underline">গৃহিণীদের জন্য</a></li>
        <li><a href="{{ url('/about') }}" class="text-leaf underline-offset-2 hover:underline">দাওয়াত সম্পর্কে</a></li>
    </ul>
    <p class="mt-5">
        যোগাযোগ: <a href="tel:{{ \App\Support\Seo::phoneIntl() }}" class="font-semibold text-ink">{{ \App\Support\Seo::phoneBangla() }}</a>
        @if (config('dawat.contact.facebook_url'))
            · <a href="{{ config('dawat.contact.facebook_url') }}" class="text-leaf underline-offset-2 hover:underline" rel="noopener">ফেসবুক গ্রুপ</a>
        @endif
    </p>
    <p class="mt-1">Dawat — homemade food in Dhaka.</p>
</footer>
