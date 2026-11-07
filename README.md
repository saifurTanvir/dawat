# দাওয়াত (Dawat)

পাড়ার গৃহিণীদের আজকের রান্না, কাছেরটা আগে। গৃহিণী পরিবারের রান্না থেকে এক-দুই প্লেট আলাদা করে পোস্ট দেন, আশেপাশের চাকরিজীবী, ছাত্র ও ব্যবসায়ীরা দেখে কল, মেসেজ বা কমেন্ট করে নেন।

- **ব্যাকএন্ড:** Laravel 13, MySQL 8 (লোকেশনের জন্য `POINT SRID 4326`), Sanctum টোকেন, spatie/laravel-permission
- **ফ্রন্টএন্ড:** Vue 3 PWA (Vue Router, Pinia, Tailwind 4), একই Laravel প্রজেক্টের ভেতরে `resources/js`
- **অ্যাডমিন:** Filament 5, `/admin`
- **API:** `/api/v1/...` — PWA আর ভবিষ্যতের নেটিভ অ্যাপ একই API ব্যবহার করবে

---

## লোকাল সেটআপ

দরকার: PHP 8.3+, Composer 2, Node 22, MySQL 8।

```bash
git clone https://github.com/saifurTanvir/dawat.git && cd dawat
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

MySQL-এ দুটো ডেটাবেস বানান (একটা অ্যাপের, একটা টেস্টের):

```sql
CREATE DATABASE dawat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE dawat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dawat'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL ON dawat.* TO 'dawat'@'localhost';
GRANT ALL ON dawat_test.* TO 'dawat'@'localhost';
```

`.env`-এ `DB_PASSWORD=secret` দিন, তারপর:

```bash
php artisan migrate --seed          # রোল, ট্যাগ আর ঢাকার ৬টা এলাকা
php artisan dawat:make-admin 017XXXXXXXX you@example.com   # /admin-এর পাসওয়ার্ড চাইবে
composer run dev                    # Laravel + Vite একসাথে
```

- অ্যাপ: http://localhost:8000
- অ্যাডমিন: http://localhost:8000/admin
- **OTP কোড:** ডেভেলপমেন্টে SMS যায় না, কোড লেখা হয় `storage/logs/laravel.log`-এ (`[OTP] 017…: 123456`)।

### টেস্ট ও কোড স্টাইল

```bash
php artisan test          # MySQL-এর dawat_test ডেটাবেসে চলে (লোকেশন কোয়েরির জন্য MySQL লাগবে)
vendor/bin/pint           # কোড ফরম্যাট
npm run build             # প্রোডাকশন বিল্ড, PWA সার্ভিস ওয়ার্কারসহ
```

---

## কাজের নিয়ম

- কাজ হয় `develop` ব্রাঞ্চে, প্রতিটি ফিচারের আলাদা কমিট: `feat(F1): …`, `fix(F12): …`, `ci: …`, `chore: …`।
- ফিচার নম্বর (F1–F23) [MVP প্ল্যান](#mvp-ফিচার) অনুযায়ী।
- ধাপ শেষে `develop` → `main` Pull Request। `main`-এ মার্জ হলে CI পাস করার পর নিজে থেকেই ডিপ্লয় হয়।
- প্রতিটি পুশে CI চলে: Pint, MySQL 8-এ সব টেস্ট, Vite বিল্ড।

### কোড কোথায়

| জায়গা | কী আছে |
| --- | --- |
| `app/Http/Controllers/Api/V1` | API কন্ট্রোলার |
| `app/Services/Otp` | OTP: `OtpSender` interface, `log` ড্রাইভার |
| `app/Support/Geo` | `GeoPoint` আর MySQL POINT পার্সিং |
| `app/Filament` | অ্যাডমিন প্যানেল |
| `app/Enums` | স্ট্যাটাস, বাংলা লেবেলসহ |
| `resources/js` | Vue PWA |
| `deploy/` | সার্ভার সেটআপ, রিলিজ আর রোলব্যাক স্ক্রিপ্ট |

### গোপনীয়তার নিয়ম (কোডে মানতে হবে)

- গৃহিণীর আসল lat/lng কখনো API-তে যাবে না। `Kitchen` ও `Post` মডেলে `location` hidden; শুধু হিসাব করা দূরত্ব পাঠানো হবে।
- OTP কোড শুধু hash করে রাখা হয়।
- আপলোড করা ছবি সবসময় `FoodPhotoProcessor` দিয়ে নতুন করে এনকোড হয়, যাতে ছবির ভেতরের GPS লোকেশন মুছে যায়। আসল ফাইল কখনো রাখা হয় না।
- টাকা সবসময় পয়সায় (`price_paisa`, ১ টাকা = ১০০)।

---

## ডিপ্লয়মেন্ট (VPS + GitHub Actions)

`main`-এ মার্জ → **CI** পাস → **Deploy** ওয়ার্কফ্লো: GitHub-এ বিল্ড করে SSH দিয়ে সার্ভারে নতুন রিলিজ ফোল্ডারে পাঠায়, মাইগ্রেশন চালায়, তারপর `current` লিংক বদলায়। তাই ডিপ্লয়ের সময় সাইট বন্ধ থাকে না, আর শেষ ৫টা রিলিজ রাখা থাকে।

### ১. সার্ভার একবার সেটআপ

Ubuntu 24.04 VPS (কমপক্ষে ২ GB RAM; সিঙ্গাপুর রিজিয়ন বাংলাদেশ থেকে দ্রুত)। ডোমেইনের A রেকর্ড সার্ভারের IP-তে দিন। তারপর সার্ভারে:

```bash
curl -fsSL https://raw.githubusercontent.com/saifurTanvir/dawat/main/deploy/setup-server.sh -o setup-server.sh
sudo bash setup-server.sh dawat.example.com you@example.com
```

এটা Nginx, PHP 8.3, MySQL, Redis, Supervisor (কিউ), cron (scheduler), HTTPS সার্টিফিকেট, `deploy` ইউজার আর `/var/www/dawat/shared/.env` বানাবে। শেষে MySQL পাসওয়ার্ড একবার দেখাবে।

### ২. ডিপ্লয়ের SSH কী

**নিজের কম্পিউটারে** নতুন একটা কী বানান (পাসফ্রেজ ছাড়া, শুধু ডিপ্লয়ের জন্য):

```bash
ssh-keygen -t ed25519 -C "github-actions-dawat" -f dawat_deploy -N ""
```

- `dawat_deploy.pub`-এর লেখাটা সার্ভারের `/home/deploy/.ssh/authorized_keys`-এ যোগ করুন।
- সার্ভারের হোস্ট কী: `ssh-keyscan -H dawat.example.com` চালিয়ে আউটপুট রাখুন।

### ৩. GitHub Secrets

রিপো → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | মান |
| --- | --- |
| `DEPLOY_HOST` | সার্ভারের IP বা ডোমেইন |
| `DEPLOY_PORT` | SSH পোর্ট (না দিলে `22`) |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_SSH_KEY` | `dawat_deploy` ফাইলের (প্রাইভেট কী) পুরো লেখা |
| `DEPLOY_PATH` | `/var/www/dawat` (না দিলে এটাই) |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan`-এর আউটপুট (সুপারিশকৃত) |

একই পাতার **Variables** ট্যাবে `APP_URL` = `https://dawat.example.com` দিলে ডিপ্লয়ের পর সাইট চালু আছে কিনা নিজে থেকে যাচাই হবে।

চাইলে **Settings → Environments → production**-এ "Required reviewers" দিয়ে প্রতিটি ডিপ্লয়ের আগে অনুমোদন চাইতে পারেন।

> প্রাইভেট কী বা পাসওয়ার্ড কখনো কোডে, চ্যাটে বা ইস্যুতে দেবেন না। শুধু GitHub Secrets আর সার্ভারের `.env`-এ।

### ৪. প্রথম ডিপ্লয়

`develop` → `main` PR মার্জ করুন, অথবা **Actions → Deploy → Run workflow**। তারপর সার্ভারে অ্যাডমিন বানান:

```bash
sudo -u deploy php /var/www/dawat/current/artisan dawat:make-admin 017XXXXXXXX you@example.com
```

### রোলব্যাক

নতুন রিলিজে সমস্যা হলে সার্ভারে:

```bash
sudo -u deploy bash /var/www/dawat/current/deploy/rollback.sh /var/www/dawat
```

এটা আগের রিলিজে ফিরে যায় আর খারাপ রিলিজ মুছে দেয়। ডেটাবেস মাইগ্রেশন নিজে থেকে উল্টায় না।

### প্রোডাকশন `.env`-এ পরে যা লাগবে

| কী | কখন |
| --- | --- |
| `OTP_DRIVER` | SMS গেটওয়ে ঠিক হলে (`app/Services/Otp`-এ নতুন ড্রাইভার যোগ হবে) |
| `MEDIA_DISK=s3` + `AWS_*` | ছবি Cloudflare R2 / DigitalOcean Spaces-এ রাখতে চাইলে |
| AI API কী | F20 (ছবি দেখে নাম ও ট্যাগ সাজেস্ট) চালু হলে |

---

## MVP ফিচার

| # | ফিচার | অবস্থা |
| --- | --- | --- |
| F1 | ফোন + OTP লগইন | ✅ |
| F2 | রোল: গৃহিণী, ক্রেতা, অ্যাডমিন | ✅ |
| F3 | গৃহিণী নিবন্ধন ও অ্যাডমিন অনুমোদন | ✅ |
| F4 | কিচেন সেটআপ: এলাকা, ম্যাপে বাসার পিন | ✅ |
| F5 | যোগাযোগের পছন্দ, শাহিদারের নম্বর | ✅ |
| F6 | ছবি প্রসেসিং (GPS মুছে WebP) | ✅ |
| F7–F9 | ৪ ধাপের পোস্ট ও প্রিভিউ কার্ড | ✅ |
| F10 | আমার পোস্ট: প্লেট বাকি, "শেষ হয়ে গেছে" | ✅ |
| F11 | অটো মেয়াদ শেষ (প্রতি ৫ মিনিটে) | ✅ |
| F12–F18 | দূরত্বভিত্তিক ফিড, ফিল্টার, QR লিংক, কল/মেসেজ/কমেন্ট, রিপোর্ট, লগ | ধাপ ৩ |
| F19 | বাংলা শেয়ার কার্ড (PNG) | ধাপ ৪ |
| F20 | AI সহায়তা (নাম ও ট্যাগ সাজেস্ট) | ধাপ ৪ |
| F21 | অ্যাডমিন: অনুমোদন, পোস্ট লুকানো, রিপোর্ট, ইউজার বন্ধ | ✅ |
| F22 | অ্যাডমিন: এলাকা, ট্যাগ, প্রিন্টযোগ্য QR | ✅ |
| F23 | অ্যাডমিন ড্যাশবোর্ড | ধাপ ৪ |
