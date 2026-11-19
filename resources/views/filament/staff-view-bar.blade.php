{{-- F71: shown while an admin looks at the panel as a staff member. --}}
@php($view = \App\Services\Admin\StaffView::current())
@if ($view)
    <div style="position:sticky;top:0;z-index:60;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.6rem 1rem;background:#1e5b3f;color:#fff;font-size:.9rem;text-align:center">
        <span>👁 আপনি এখন <strong>{{ $view['name'] }}</strong> (স্টাফ) হিসেবে দেখছেন। যা করবেন, তাঁর নামে লেখা হবে।</span>
        <a href="{{ route('admin.staff-view.stop') }}" style="background:#fff;color:#1e5b3f;border-radius:.5rem;padding:.25rem .75rem;font-weight:600;text-decoration:none">অ্যাডমিনে ফিরুন</a>
    </div>
@endif
