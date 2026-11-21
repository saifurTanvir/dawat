<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner, 21 Nov: "শাহিদার" is a Cumilla word people did not understand. The role is now
 * "এলাকা ম্যানেজার" (খাবার নেওয়া ও পৌঁছানো). The bot learns the new name, the new join link,
 * and that people may still say the old word.
 */
return new class extends Migration
{
    private const TOPIC = 'এলাকা ম্যানেজার (আগের নাম শাহিদার)';

    public function up(): void
    {
        $this->swap('শাহিদার', 'এলাকা ম্যানেজার', 'dawat.cloud/join/shahidar', 'dawat.cloud/join/area-manager');

        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'all', 'feature' => null, 'topic' => self::TOPIC,
            'body' => 'দাওয়াতে যিনি এলাকার দায়িত্বে থাকেন তাঁকে এখন "এলাকা ম্যানেজার" বলা হয় — কাজ: খাবার নেওয়া ও পৌঁছানো। সকালে গৃহিণী আর গ্রাহকদের সাথে আজকের খাবার মিলিয়ে নেন, তারপর গৃহিণীর বাসা থেকে বক্স নিয়ে গ্রাহকের বাসা বা অফিসে পৌঁছে দেন; কোনো সমস্যা হলে গৃহিণী বা গ্রাহক তাঁকেই বলবেন। আগে এই কাজকে "শাহিদার" বলা হতো — কেউ শাহিদার বা ডেলিভারি ম্যান বললে বুঝবেন তিনি এলাকা ম্যানেজারের কথা বলছেন, আর উত্তরে "এলাকা ম্যানেজার" বলবেন।',
            'is_active' => true, 'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 10,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->delete();
        $this->swap('এলাকা ম্যানেজার', 'শাহিদার', 'dawat.cloud/join/area-manager', 'dawat.cloud/join/shahidar');
        cache()->forget('bot-facts:prompt');
    }

    private function swap(string $from, string $to, string $fromLink, string $toLink): void
    {
        foreach (DB::table('bot_facts')->get(['id', 'topic', 'body']) as $fact) {
            $topic = str_replace($from, $to, $fact->topic);
            $body = str_replace([$fromLink, $from], [$toLink, $to], $fact->body);
            if ($topic !== $fact->topic || $body !== $fact->body) {
                DB::table('bot_facts')->where('id', $fact->id)->update(['topic' => $topic, 'body' => $body, 'updated_at' => now()]);
            }
        }
    }
};
