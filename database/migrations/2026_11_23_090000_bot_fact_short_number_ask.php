<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F109 (owner, 23 Nov): the number ask and the talk button go as two short messages, not one long one. */
return new class extends Migration
{
    private const TOPIC = 'নম্বর দিতে না চাইলে';

    public function up(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->update([
            'body' => 'গ্রাহকের কাছে নম্বর চাইবেন দুইটা আলাদা ছোট মেসেজে — প্রথমটায়: আগ্রহী হলে মোবাইল নম্বর দিন, টিম ফোন করে কথা বলবে; দ্বিতীয়টায়: নম্বর দিতে না চাইলে নিচের "আগ্রহী — কথা বলুন" বোতামটা চাপুন, টিম এখানেই Messenger-এ কথা বলবে। লম্বা মেসেজ দেবেন না, নম্বর দেওয়ার জন্য চাপও দেবেন না।',
            'updated_at' => now(),
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('topic', self::TOPIC)->update([
            'body' => 'কোনো গ্রাহক মোবাইল নম্বর দিতে না চাইলে বলবেন: নিচের "আগ্রহী — কথা বলুন" বোতামটা চাপুন, আমাদের টিম এখানেই Messenger-এ কল বা মেসেজ দিয়ে কথা বলবে। নম্বর দেওয়ার জন্য চাপ দেবেন না।',
            'updated_at' => now(),
        ]);
        cache()->forget('bot-facts:prompt');
    }
};
