<?php

use App\Services\Ads\BaddaAds;
use App\Services\Geo\FocusSplit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** F106 (owner, 23 Nov): tag would-be customers as Badda or not; the call list shows Badda by default. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->string('tag', 40)->nullable()->index()->after('source');
            $table->boolean('in_badda')->default(false)->index()->after('tag');
        });

        $badda = DB::table('messenger_threads')->whereNotNull('call_request_id')->get(['call_request_id', 'id']);
        foreach (DB::table('call_requests')->where('reason', 'like', 'নতুন গ্রাহক%')->get(['id', 'reason']) as $r) {
            $fromAd = $badda->where('call_request_id', $r->id)->isNotEmpty()
                && DB::table('messenger_messages')->whereIn('messenger_thread_id', $badda->where('call_request_id', $r->id)->pluck('id'))
                    ->where('direction', 'in')->whereIn('text', BaddaAds::ADS['customer']['questions'])->exists();
            $tag = $fromAd || preg_match(BaddaAds::BADDA_WORDS, (string) $r->reason) ? 'বাড্ডা গ্রাহক' : 'অন্য এলাকার গ্রাহক';
            DB::table('call_requests')->where('id', $r->id)->update(['tag' => $tag]);
        }

        // Badda by tag, by the person's kitchen area, or by what they wrote.
        $areaIds = app(FocusSplit::class)->focusAreaIds();
        foreach (DB::table('call_requests')->get(['id', 'tag', 'reason', 'user_id']) as $r) {
            $kitchenArea = $r->user_id ? DB::table('kitchens')->where('user_id', $r->user_id)->value('area_id') : null;
            $in = $r->tag === 'বাড্ডা গ্রাহক' || in_array($kitchenArea, $areaIds) || preg_match(BaddaAds::BADDA_WORDS, (string) $r->reason);
            DB::table('call_requests')->where('id', $r->id)->update(['in_badda' => (bool) $in]);
        }
    }

    public function down(): void
    {
        Schema::table('call_requests', function (Blueprint $table) {
            $table->dropColumn(['tag', 'in_badda']);
        });
    }
};
