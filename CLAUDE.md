# Dawat — notes for AI assistants

Homemade-food marketplace for Dhaka. Laravel 13 + MySQL 8 API (`/api/v1`), Vue 3 PWA in `resources/js`, Filament 5 admin at `/admin`. The product owner writes in Bangla; all user-facing text is Bangla.

## Rules

- Work on `develop`; one commit per feature, message `feat(F<n>): …` (feature numbers are in README → MVP ফিচার). PR `develop` → `main`; merging to `main` deploys.
- Run `vendor/bin/pint` and `php artisan test` before committing. Tests need MySQL (spatial functions); `phpunit.xml` points at the `dawat_test` database.
- Never expose a kitchen's or post's `location` (lat/lng) in any API response. Send rounded distances only.
- Money is integer paisa (`price_paisa`).
- Phone numbers are stored normalised as `01XXXXXXXXX` via `App\Support\PhoneNumber`.
- New SMS gateways implement `App\Services\Otp\OtpSender` and are bound in `AppServiceProvider` by `OTP_DRIVER`.
- Locations: `App\Support\Geo\GeoPoint` + `App\Casts\AsGeoPoint`; MySQL stores SRID 4326 points as X=longitude, Y=latitude.
- Enums in `app/Enums` carry Bangla labels (`label()`) and Filament badge colours.
- Never commit secrets. Deploy credentials live in GitHub Actions secrets; production settings in `/var/www/dawat/shared/.env`.
