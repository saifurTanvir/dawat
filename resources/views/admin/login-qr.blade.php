<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>লগইন QR — দাওয়াত</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #FBF8F1; color: #1d2b22; text-align: center; padding: 24px 16px; }
        .qr { width: min(80vw, 360px); margin: 16px auto; background: #fff; padding: 16px; border-radius: 16px; }
        .qr svg { width: 100%; height: auto; display: block; }
        p { max-width: 420px; margin: 8px auto; }
        code { word-break: break-all; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <h1 style="font-size:22px">ফোনের ক্যামেরা দিয়ে স্ক্যান করুন</h1>
    <div class="qr">{!! $svg !!}</div>
    <p>স্ক্যান করলে নিজের অ্যাকাউন্টে ঢুকে যাবেন। লিংকটা এক বারই কাজ করবে, ৩০ মিনিট।</p>
    <p><code>{{ $url }}</code></p>
</body>
</html>
