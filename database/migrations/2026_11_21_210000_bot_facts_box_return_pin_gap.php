<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner, 21 Nov 21:09: boxes come back — each customer has two boxes that take turns; the area manager
 * brings the empty one back on the next pickup or when collecting money. Location reminders now go
 * once every 2–3 days, and the pin is taken as she sets it on the map.
 */
return new class extends Migration
{
    private const FACTS = [
        ['all', 'বক্স ফেরত আসে কিনা', 'হ্যাঁ, বক্স ফেরত আসে। প্রতিটা গ্রাহকের জন্য দুইটা বক্স থাকে — একটায় খাবার যায়, আর এলাকা ম্যানেজার পরের বার খাবার নিতে আসার সময় বা টাকা নিতে আসার দিন আগের খালি বক্সটা ফেরত নিয়ে আসেন। এভাবে দুইটা বক্স পালা করে চলে; গৃহিণী বা গ্রাহক কাউকে বক্স কিনতে হয় না।'],
        ['cook', 'লোকেশন মনে করানো', 'বাসার লোকেশন না দিলে ২–৩ দিনে একবার মনে করিয়ে দেওয়া হয়, এর বেশি না। ম্যাপে নিজে যেখানে পিন বসান সেটাই নেওয়া হয় — একটু এদিক-ওদিক হলেও চলবে, আবার ঠিক করতে হবে না।'],
    ];

    public function up(): void
    {
        $now = now();
        $sort = (int) DB::table('bot_facts')->max('sort_order');
        foreach (self::FACTS as [$audience, $topic, $body]) {
            DB::table('bot_facts')->insert([
                'audience' => $audience, 'feature' => null, 'topic' => $topic, 'body' => $body,
                'is_active' => true, 'sort_order' => $sort += 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->whereIn('topic', array_column(self::FACTS, 1))->delete();
        cache()->forget('bot-facts:prompt');
    }
};
