<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner, 21 Nov: the bot called some men "আপু" and some women "ভাই". Nobody is called আপু/আপা/ভাই
 * any more — everyone is "আপনি", politely, without a title. The facts lose "ডেলিভারি ভাই" (now just
 * "এলাকা ম্যানেজার") and learn the addressing rule.
 */
return new class extends Migration
{
    private const TOPIC = 'সম্বোধন — আপু/ভাই নয়';

    /** Longest first, so the bracketed forms go before the plain words. */
    private const SWAPS = [
        'ডেলিভারি ভাইকে (এলাকা ম্যানেজার)' => 'এলাকা ম্যানেজারকে',
        'ডেলিভারি ভাই (এলাকা ম্যানেজার)' => 'এলাকা ম্যানেজার',
        'এলাকা ম্যানেজার (ডেলিভারি ভাই)' => 'এলাকা ম্যানেজার',
        'ডেলিভারি ভাইকে' => 'এলাকা ম্যানেজারকে',
        'ডেলিভারি ভাই' => 'এলাকা ম্যানেজার',
        ' আপু,' => ',',
        ' আপু' => '',
        'আপু, ' => '',
    ];

    public function up(): void
    {
        foreach (DB::table('bot_facts')->get(['id', 'topic', 'body']) as $fact) {
            $topic = strtr((string) $fact->topic, self::SWAPS);
            $body = strtr((string) $fact->body, self::SWAPS);
            if ($topic !== $fact->topic || $body !== $fact->body) {
                DB::table('bot_facts')->where('id', $fact->id)->update(['topic' => $topic, 'body' => $body, 'updated_at' => now()]);
            }
        }

        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'all', 'feature' => null, 'topic' => self::TOPIC,
            'body' => 'কাউকে "আপু", "আপা", "ভাই", "ভাইয়া", "স্যার" বা "ম্যাডাম" বলবেন না — কে পুরুষ আর কে নারী, লেখা দেখে বোঝা যায় না, আর ভুল হলে মানুষ কষ্ট পান। সবাইকে সম্মান দিয়ে "আপনি" বলে কথা বলবেন, কোনো উপাধি ছাড়া। নাম জানা থাকলে শুধু নাম বলা যায়।',
            'is_active' => true, 'sort_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->delete();
        cache()->forget('bot-facts:prompt');
    }
};
