<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F112 (owner, 26 Nov): cooks said the map does not show their exact house; the map now has a satellite photo and a fixed middle pin. */
return new class extends Migration
{
    private const TOPIC = 'ম্যাপে বাসা খুঁজে না পেলে';

    public function up(): void
    {
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'cook', 'feature' => 'F112', 'topic' => self::TOPIC,
            'body' => 'কোনো গৃহিণী বললে ম্যাপে তাঁর বাসার ঠিক জায়গা পাচ্ছেন না: ম্যাপের উপরে "স্যাটেলাইট" চাপলে উপর থেকে বাড়ির ছাদ দেখা যায়। ম্যাপটা আঙুল দিয়ে সরিয়ে মাঝখানের পিনটা নিজের বাড়ির ছাদের উপর আনবেন, দরকার হলে ম্যাপ বড় (জুম) করে দেখবেন। "আমি এখন বাসায় আছি" চাপলে কয়েক সেকেন্ড অপেক্ষা করতে বলবেন; বারান্দা বা জানালার কাছে থাকলে লোকেশন বেশি ঠিক আসে, আর নীল বৃত্তটা দেখায় লোকেশন কতটা এদিক-ওদিক হতে পারে। তবু না পারলে বলবেন, টিম ফোনে কথা বলে ঠিক করে দেবে। নিজে থেকে লোকেশন চাইবেন না।',
            'is_active' => true, 'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 10,
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
