{{-- F80: what to paste into Meta's app dashboard, and which keys the server has. Admins only. --}}
<div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px;">
    <div>
        <div style="font-weight: 600;">Callback URL</div>
        <code style="user-select: all; display: block; padding: 6px 8px; border-radius: 6px; background: rgba(127,127,127,.15); word-break: break-all;">{{ $url }}</code>
    </div>
    <div>
        <div style="font-weight: 600;">Verify token</div>
        <code style="user-select: all; display: block; padding: 6px 8px; border-radius: 6px; background: rgba(127,127,127,.15); word-break: break-all;">{{ $verify }}</code>
        <div style="font-size: 12px; opacity: .7;">এটা শুধু Meta-র Webhook ঘরে বসাবেন, আর কাউকে দেবেন না।</div>
    </div>
    <div>
        <div style="font-weight: 600;">Webhook fields</div>
        <code>messages, messaging_postbacks, message_echoes</code>
    </div>
    <div>
        <div style="font-weight: 600; margin-bottom: 4px;">সার্ভারে যা আছে</div>
        @foreach ($checks as $label => $ok)
            <div>{{ $ok ? '✅' : '❌' }} {{ $label }}</div>
        @endforeach
        <div style="font-size: 12px; opacity: .7; margin-top: 4px;">key-গুলো GitHub → Settings → Secrets → Actions-এ রাখুন; পরের deploy-এ সার্ভারে চলে যাবে।</div>
    </div>
    <div style="font-size: 12px; opacity: .7;">বট যে রান্নাঘর যোগ করে, সেগুলো অনুমোদন হয় {{ $approver ?? 'প্রথম অ্যাডমিন' }}-এর নামে।</div>
</div>
