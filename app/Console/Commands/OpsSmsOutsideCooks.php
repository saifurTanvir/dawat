<?php

namespace App\Console\Commands;

use App\Enums\KitchenStatus;
use App\Models\Kitchen;
use App\Services\Geo\FocusSplit;
use App\Services\Otp\BulkSmsBdSender;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * F101 (owner, 23 Nov): a thank-you SMS to cooks outside Badda — we start in Badda and
 * will reach their area later. Without --send it only counts; --test sends to one number.
 */
class OpsSmsOutsideCooks extends Command
{
    public const CAMPAIGN = 'outside-thanks-1';

    public const TEXT = 'দাওয়াতে যোগ দেওয়ায় ধন্যবাদ। আমরা আগে বাড্ডায় শুরু করেছি; ইনশাআল্লাহ শিগগিরই আপনার এলাকায় আসব, তখন আমরাই যোগাযোগ করব। — দাওয়াত';

    /** Bangla SMS: 70 characters in one part, 67 in each part of a longer one. */
    protected $signature = 'ops:sms-outside-cooks {--test= : send only to this number} {--send : send to everyone not yet sent} {--limit=1000}';

    protected $description = 'Thank-you SMS to cooks outside Badda (counts only unless --send or --test)';

    public function handle(BulkSmsBdSender $sms): int
    {
        $parts = mb_strlen(self::TEXT) <= 70 ? 1 : (int) ceil(mb_strlen(self::TEXT) / 67);
        $this->line('Text: '.mb_strlen(self::TEXT).' chars, '.$parts.' SMS parts each');
        try {
            $this->line('Balance: '.($sms->balance() ?? 'unknown'));
        } catch (Throwable $e) {
            $this->line('Balance: unknown ('.class_basename($e).')');
        }

        if ($test = $this->option('test')) {
            $sms->sendText((string) PhoneNumber::normalize($test), self::TEXT);
            $this->info('Test SMS sent to '.substr((string) PhoneNumber::normalize($test), 0, 5).'XXXXXX');

            return self::SUCCESS;
        }

        $phones = $this->phones();
        $this->line('To send: '.$phones->count().' numbers ('.$phones->count() * $parts.' SMS parts)');
        if (! $this->option('send')) {
            return self::SUCCESS;
        }

        $sent = $failed = 0;
        foreach ($phones->take((int) $this->option('limit')) as $phone) {
            try {
                $sms->sendText($phone, self::TEXT);
                DB::table('sms_broadcasts')->insertOrIgnore(['campaign' => self::CAMPAIGN, 'phone' => $phone, 'sent_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn('Failed: '.$e->getMessage());
                if (str_contains($e->getMessage(), 'balance')) {
                    break;
                }
            }
            Sleep::for(300)->milliseconds();
        }
        $this->info("Sent {$sent}, failed {$failed}");

        return self::SUCCESS;
    }

    /** Normalised numbers of approved/pending cooks outside the focus area, not yet sent. */
    public function phones()
    {
        $done = DB::table('sms_broadcasts')->where('campaign', self::CAMPAIGN)->pluck('phone')->flip();

        return Kitchen::query()
            ->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])
            ->where(fn ($q) => $q->whereNotIn('area_id', app(FocusSplit::class)->focusAreaIds())->orWhereNull('area_id'))
            ->with('user:id,phone')->get()
            ->map(fn (Kitchen $k) => PhoneNumber::normalize($k->user?->phone))
            ->filter()->unique()->reject(fn ($p) => $done->has($p))->values();
    }
}
