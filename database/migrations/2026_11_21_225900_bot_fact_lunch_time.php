<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner, 21 Nov 22:58: lunch is delivered from 12:30, everyone has it before 1 pm. */
return new class extends Migration
{
    private const TOPIC = 'দুপুরের খাবার কখন পৌঁছায়';

    public function up(): void
    {
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'customer', 'feature' => null, 'topic' => self::TOPIC,
            'body' => 'দুপুরের খাবার সাড়ে ১২টা থেকে পৌঁছানো শুরু হয়, আর ইনশাআল্লাহ ১টার আগেই সবার বাসা বা অফিসে পৌঁছে যায়।',
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
