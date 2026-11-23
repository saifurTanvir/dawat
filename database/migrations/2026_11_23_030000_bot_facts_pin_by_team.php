<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F103 (owner, 23 Nov 08:57): the bot no longer asks cooks for their home pin; the team takes it on a call. */
return new class extends Migration
{
    private const TOPIC = 'বাসার লোকেশন (পিন)';

    public function up(): void
    {
        DB::table('bot_facts')->where('topic', 'লোকেশন মনে করানো')->update(['is_active' => false, 'updated_at' => now()]);
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'cook', 'feature' => 'F103', 'topic' => self::TOPIC,
            'body' => 'এখন বাসার লোকেশন (পিন) চাইবেন না, লোকেশন লিংকও পাঠাবেন না। দরকার হলে টিম ফোনে কথা বলে লোকেশন নিয়ে নেবে। কেউ নিজে থেকে লোকেশন দিতে চাইলে ধন্যবাদ জানাবেন।',
            'is_active' => true, 'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 10,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->delete();
        DB::table('bot_facts')->where('topic', 'লোকেশন মনে করানো')->update(['is_active' => true]);
        cache()->forget('bot-facts:prompt');
    }
};
