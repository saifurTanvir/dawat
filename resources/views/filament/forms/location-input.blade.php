{{-- F62: home location — link, current location, or address search, with a draggable pin. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        data-no-mic
        class="dl-box"
        x-data="dawatLocation({ state: $wire.$entangle('{{ $getStatePath() }}'), searchUrl: @js(route('admin.geocode')) })"
        x-init="init()"
    >
        <input type="text" class="dl-input" x-model.lazy="state" @change="fromText()"
            placeholder="গুগল ম্যাপ / হোয়াটসঅ্যাপ লোকেশনের লিংক, বা 23.78, 90.42" />
        <div class="dl-row">
            <button type="button" class="dl-btn dl-primary" @click="here()" :disabled="busy">
                <span x-text="busy === 'here' ? 'খোঁজা হচ্ছে…' : '📍 এখানকার লোকেশন'"></span>
            </button>
            <input type="text" class="dl-input dl-grow" x-model="query" @keydown.enter.prevent="search()"
                placeholder="বা ঠিকানা লিখুন, যেমন: হোসেন মার্কেট, উত্তর বাড্ডা" />
            <button type="button" class="dl-btn" @click="search()" :disabled="busy || query.length < 3">
                <span x-text="busy === 'search' ? 'খোঁজা হচ্ছে…' : 'ঠিকানা খুঁজুন'"></span>
            </button>
        </div>
        <p x-show="message" x-text="message" class="dl-msg"></p>
        <ul x-show="results.length" class="dl-results">
            <template x-for="r in results" :key="r.lat + ',' + r.lng">
                <li><button type="button" @click="pick(r)" x-text="r.label"></button></li>
            </template>
        </ul>
        <div x-ref="map" x-show="point" class="dl-map" wire:ignore></div>
        <p x-show="point" class="dl-hint">
            পিনটা টেনে বা ম্যাপে চাপ দিয়ে ঠিক বাসার ওপর বসান ·
            <a :href="point ? `https://www.google.com/maps?q=${point.lat},${point.lng}` : '#'" target="_blank" rel="noopener">গুগল ম্যাপে দেখুন</a>
        </p>
    </div>
</x-dynamic-component>
