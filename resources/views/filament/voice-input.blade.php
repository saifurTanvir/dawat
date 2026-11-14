{{-- F58: Bangla voice typing for the quick-add forms. Chrome (Android, desktop) and
     Safari have speech recognition; elsewhere no mic button is shown. The text is
     written into the box as if typed, so Livewire sees it. Phone and number boxes
     get Western digits without spaces. --}}
<style>
    .dawat-mic { position:absolute; inset-inline-end:.35rem; top:50%; transform:translateY(-50%); z-index:5;
        width:2rem; height:2rem; border-radius:9999px; display:grid; place-items:center;
        color:rgb(30 91 63); background:rgb(223 233 226); border:0; cursor:pointer; }
    .dawat-mic[data-on] { color:#fff; background:rgb(184 50 42); animation:dawat-pulse 1.2s infinite; }
    .dawat-mic-wrap { position:relative; }
    .dawat-mic-wrap > input, .dawat-mic-wrap > textarea { padding-inline-end:2.75rem !important; }
    textarea + .dawat-mic { top:.5rem; transform:none; }
    @keyframes dawat-pulse { 50% { opacity:.6 } }
    @media (prefers-reduced-motion: reduce) { .dawat-mic[data-on] { animation:none } }
</style>
<script>
(() => {
    const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Rec) return;
    const BN = '০১২৩৪৫৬৭৮৯';
    const ascii = (s) => s.replace(/[০-৯]/g, (d) => String(BN.indexOf(d)));
    const mic = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';
    let active = null;

    function put(field, text) {
        const numeric = field.type === 'tel' || field.type === 'number' || field.inputMode === 'tel' || field.inputMode === 'numeric';
        let value = numeric ? ascii(text).replace(/[^\d.,+]/g, '') : text.trim();
        if (!numeric && field.value.trim()) value = field.value.trim() + ' ' + value;
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function listen(field, button) {
        if (active) { active.stop(); return; }
        const rec = new Rec();
        rec.lang = button.closest('[data-voice-form]')?.dataset.voiceForm || 'bn-BD';
        rec.interimResults = false;
        rec.maxAlternatives = 1;
        rec.onresult = (e) => put(field, e.results[0][0].transcript);
        rec.onend = () => { button.removeAttribute('data-on'); button.setAttribute('aria-label', 'বলে লিখুন'); active = null; };
        button.setAttribute('data-on', '');
        button.setAttribute('aria-label', 'শুনছি… থামাতে চাপুন');
        active = rec;
        rec.start();
    }

    function attach(root) {
        root.querySelectorAll('[data-voice-form] input[type=text], [data-voice-form] input[type=tel], [data-voice-form] input[type=number], [data-voice-form] input:not([type]), [data-voice-form] textarea').forEach((field) => {
            if (field.dataset.micAdded || field.readOnly || field.closest('.fi-select-input, [role=combobox]')) return;
            field.dataset.micAdded = '1';
            const wrap = field.parentElement;
            wrap.classList.add('dawat-mic-wrap');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dawat-mic';
            button.innerHTML = mic;
            button.title = 'বলে লিখুন (বাংলায়)';
            button.setAttribute('aria-label', 'বলে লিখুন');
            button.addEventListener('click', (e) => { e.preventDefault(); listen(field, button); });
            field.insertAdjacentElement('afterend', button);
        });
    }

    new MutationObserver(() => attach(document)).observe(document.body, { childList: true, subtree: true });
    attach(document);
})();
</script>
