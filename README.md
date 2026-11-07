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
php artisan db:seed --class=DemoSeeder   # (ইচ্ছা হলে) মিরপুর ১০-এর আশেপাশে ৫টা ডেমো রান্নাঘর ও পোস্ট
composer run dev                    # Laravel + Vite একসাথে
```

- অ্যাপ: http://localhost:8000
- অ্যাডমিন: http://localhost:8000/admin
- **OTP কোড:** লোকালে যেকোনো ৬ অঙ্কের কোড চলে (যেমন `123456`), লগইন আর শাহিদারের নম্বর যাচাই দুটোতেই। এটা চালু থাকে `.env`-এর `OTP_ANY_CODE=true` দিয়ে, আর কাজ করে শুধু `APP_ENV=local` হলে; প্রোডাকশন, স্টেজিং বা টেস্টে সেটিং থাকলেও উপেক্ষা হয়। আসল কোডটাও লেখা থাকে `storage/logs/laravel.log`-এ (`[OTP] 017…: 948609`), বন্ধ করে দেখতে চাইলে `OTP_ANY_CODE=false` দিন।

- **টেস্ট ইউজার:** `php artisan db:seed --class=TestUsersSeeder` চালালে প্রতিটা রোলের একজন করে ইউজার তৈরি হয় (শুধু `APP_ENV=local`-এ চলে)। অ্যাডমিন প্যানেলে ইমেইল + পাসওয়ার্ড `123456`; অ্যাপে ফোন নম্বর + যেকোনো OTP।

  | রোল | ফোন (অ্যাপ) | ইমেইল (অ্যাডমিন প্যানেল) |
  | --- | --- | --- |
  | অ্যাডমিন | 01900000000 | admin@dawat.test |
  | গৃহিণী (অনুমোদিত) | 01800000001 | cook@dawat.test |
  | গৃহিণী (অনুমোদনের অপেক্ষায়) | 01800000002 | pending-cook@dawat.test |
  | ক্রেতা | 01800000003 | buyer@dawat.test |

### শেয়ার কার্ড (F19) লোকালি

কার্ড বানায় headless Chrome। লোকালে চাইলে চালু করুন (না করলেও অ্যাপ চলে, শুধু কার্ড তৈরি হয় না):

```bash
mkdir -p ~/browsershot && cd ~/browsershot && npm init -y && PUPPETEER_SKIP_DOWNLOAD=1 npm i puppeteer@25
```

তারপর `.env`-এ: `SHARE_CARDS_ENABLED=true`, `SHARE_CARD_CHROME_PATH=` (আপনার Chrome-এর পথ), `SHARE_CARD_NODE_MODULES=$HOME/browsershot/node_modules`। কার্ড কিউতে তৈরি হয়, তাই `php artisan queue:work` চালু রাখুন (`composer run dev` নিজেই চালায়)। সার্ভারে `setup-server.sh` এসব নিজে করে।

### AI সাজেশন (F20)

চালু করতে `.env`-এ `AI_DRIVER=anthropic` আর `ANTHROPIC_API_KEY=` দিন (মডেল বদলাতে `AI_MODEL`)। তখন পোস্ট লেখার সময় প্রথম ছবি দেখে খাবারের নাম আর কিছু ট্যাগ আগে থেকে বসে যায়, ছবি অন্ধকার বা ঝাপসা হলে সতর্ক করে, আর নোটের নিচে "লেখাটা গুছিয়ে দিন" বোতাম আসে (আগের লেখা ফেরত আনা যায়)। যা মানা হয়:

- AI খাবারের ছবি বানায় না, বদলায় না। ছবি ছোট JPEG করে, মেটাডেটা/GPS মুছে পাঠানো হয়, কোথাও রাখা হয় না।
- গৃহিণী যা লিখেছেন তার ওপর লেখে না; সাজেশন শুধু খালি ঘরে বসে।
- হালাল, ডায়াবেটিক-বান্ধব, বাদাম/চিংড়ি আছে কখনো সাজেস্ট করে না।
- নোট গোছানোর সময় লবণ-ঝাল-তেলের মতো কোনো সতর্কতা বাদ দেয় না।
- প্রতি গৃহিণী দিনে ৪০ বার। AI না চললে বা কী না থাকলে উইজার্ড আগের মতোই চলে।

### টেস্ট ও কোড স্টাইল

```bash
php artisan test          # MySQL-এর dawat_test ডেটাবেসে চলে (লোকেশন কোয়েরির জন্য MySQL লাগবে)
vendor/bin/pint           # কোড ফরম্যাট
npm run build             # প্রোডাকশন বিল্ড, PWA সার্ভিস ওয়ার্কারসহ
```

---

## কাজের নিয়ম

- কাজ হয় `develop` ব্রাঞ্চে, প্রতিটি ফিচারের আলাদা কমিট: `feat(F1): …`, `fix(F12): …`, `ci: …`, `chore: …`।
- ফিচার নম্বর (F1–F28) [MVP প্ল্যান](#mvp-ফিচার) অনুযায়ী।
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
- রাঁধুনির ফোন নম্বর কোনো তালিকা বা পেজে যায় না; শুধু ক্রেতা কল/মেসেজ চাপলে `POST /posts/{post}/contact` থেকে দেওয়া হয়, তাও রাঁধুনি সেই বাটন চালু রাখলে।
- ক্রেতাকে দেখানো দূরত্ব ৫০ মিটারে রাউন্ড করা, কখনো ১০০ মিটারের কম নয় (`PublicDistance`)।
- OTP কোড শুধু hash করে রাখা হয়।
- আপলোড করা ছবি সবসময় `FoodPhotoProcessor` দিয়ে নতুন করে এনকোড হয়, যাতে ছবির ভেতরের GPS লোকেশন মুছে যায়। আসল ফাইল কখনো রাখা হয় না।
- টাকা সবসময় পয়সায় (`price_paisa`, ১ টাকা = ১০০)।

---

## ডিপ্লয়মেন্ট (VPS + GitHub Actions)

`main`-এ মার্জ → **CI** পাস → **Deploy** ওয়ার্কফ্লো: GitHub-এ বিল্ড করে SSH দিয়ে সার্ভারে নতুন রিলিজ ফোল্ডারে পাঠায়, মাইগ্রেশন চালায়, তারপর `current` লিংক বদলায়। তাই ডিপ্লয়ের সময় সাইট বন্ধ থাকে না, আর শেষ ৫টা রিলিজ রাখা থাকে।

### ১. সার্ভার একবার সেটআপ

Ubuntu 24.04 VPS (কমপক্ষে ২ GB RAM; Hostinger KVM 1, India লোকেশন)। ডোমেইনের A রেকর্ড সার্ভারের IP-তে দিন। তারপর সার্ভারে:

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
| `BULKSMSBD_API_KEY`, `BULKSMSBD_SENDER_ID` | প্রথম ডিপ্লয়ের আগেই (BulkSMSBD প্যানেল থেকে)। যাচাই: `php artisan dawat:sms-test 017XXXXXXXX`। প্যানেলে IP whitelist চালু থাকলে সার্ভারের IP যোগ করুন |
| `MEDIA_DISK=s3` + `AWS_*` | ছবি Cloudflare R2 / DigitalOcean Spaces-এ রাখতে চাইলে |
| `ORDER_SMS_ENABLED` | নিয়মিত গ্রাহকের বুকিং/রান্না-নেই SMS (ডিফল্ট `true`, প্রতি SMS প্রায় ৳০.৩৫) |
| `AI_DRIVER=anthropic` + `ANTHROPIC_API_KEY` | F20 AI সাজেশন চালু করতে (না দিলে বোতামগুলো দেখায় না, বাকি সব চলে) |

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
| F12–F14 | দূরত্বভিত্তিক ফিড, দূরত্ব ও বেলার ফিল্টার, এলাকা | ✅ |
| F15 | প্রিন্ট করা QR থেকে এলাকার ফিড, স্ক্যান গণনা | ✅ |
| F16, F18 | কল / হোয়াটসঅ্যাপ / শেয়ার বাটন ও যোগাযোগের লগ | ✅ |
| F17 | পোস্টের পেজ, কমেন্ট, রিপোর্ট (৩ জন রিপোর্ট করলে লুকানো) | ✅ |
| F19 | বাংলা শেয়ার কার্ড, লিংকের প্রিভিউ (Open Graph) | ✅ |
| F20 | AI সহায়তা: ছবি দেখে নাম ও ট্যাগ, ছবির মান যাচাই, নোট গুছিয়ে দেওয়া (ছবি বানায় বা বদলায় না) | ✅ |
| F21 | অ্যাডমিন: অনুমোদন, পোস্ট লুকানো, রিপোর্ট, ইউজার বন্ধ | ✅ |
| F22 | অ্যাডমিন: এলাকা, ট্যাগ, প্রিন্টযোগ্য QR | ✅ |
| F23 | অ্যাডমিন ড্যাশবোর্ড: আজকের হিসাব, প্রতিদিনের গ্রাফ, জনপ্রিয় রান্নাঘর ও QR | ✅ |

### নিয়মিত গ্রাহক, বুকিং ও ডেলিভারি

| # | ফিচার | অবস্থা |
| --- | --- | --- |
| F24 | শাহিদার রোল; প্রতিটা এলাকা = ডেলিভারির জোন, তাতে এক বা একাধিক শাহিদার | ✅ |
| F25 | নিয়মিত গ্রাহক (অ্যাপে অনুরোধ বা অ্যাডমিন যোগ করে), গৃহিণীর সর্বোচ্চ গ্রাহক, গ্রাহক ↔ গৃহিণী ↔ শাহিদার জুটি ও বদলের ইতিহাস | ✅ |
| F26 | আগাম বুকিং: পোস্ট দিলেই জুটির গ্রাহকদের প্লেট আগে কাটা, SMS-এ বক্স নম্বর আর ডেলিভারি কোড; "আজ লাগবে না", বিরতি, "আজ রান্না নেই"; বাকি প্লেট ফিডে | ✅ |
| F27 | শাহিদারের আজকের তালিকা (পিকআপ, ডেলিভারি, পোস্ট আসেনি), আগে ছাপানো QR স্টিকার পিকআপে স্ক্যান করে বক্সে জোড়া, তিনভাবে ডেলিভারি নিশ্চিত: গ্রাহকের স্ক্যান, SMS কোড, বা "দিয়েছি" (যাচাই বাকি) | ✅ |
