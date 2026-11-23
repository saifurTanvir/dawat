<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F107 (owner, 23 Nov): customers who do not want to give a number can tap "আগ্রহী — কথা বলুন". */
return new class extends Migration
{
    private const TOPIC = 'নম্বর দিতে না চাইলে';

    public function up(): void
    {
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'customer', 'feature' => 'F107', 'topic' => self::TOPIC,
            'body' => 'কোনো গ্রাহক মোবাইল নম্বর দিতে না চাইলে বলবেন: নিচের "আগ্রহী — কথা বলুন" বোতামটা চাপুন, আমাদের টিম এখানেই Messenger-এ কল বা মেসেজ দিয়ে কথা বলবে। নম্বর দেওয়ার জন্য চাপ দেবেন না।',
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
