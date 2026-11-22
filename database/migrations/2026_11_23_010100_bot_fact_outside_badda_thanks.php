<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F101 (owner, 23 Nov): what cooks outside Badda are told when they join, wait or ask when it starts. */
return new class extends Migration
{
    private const TOPIC = 'বাড্ডার বাইরের গৃহিণী — কবে শুরু';

    public function up(): void
    {
        $now = now();
        DB::table('bot_facts')->insert([
            'audience' => 'cook', 'feature' => 'F101', 'topic' => self::TOPIC,
            'body' => 'বাড্ডার বাইরের কোনো গৃহিণী নতুন যোগ দিলে, কবে শুরু হবে জানতে চাইলে বা অপেক্ষায় হতাশা দেখালে পুরো কথাটা আন্তরিকভাবে বলবেন: যোগ দেওয়া আর ধৈর্য ধরে অপেক্ষার জন্য কৃতজ্ঞতা — তাঁর অপেক্ষাকে আমরা মূল্য দিই। দাওয়াত শুধু ব্যবসা নয়: আমরা চাই তাঁর হাতের রান্না সম্মানের সাথে নিয়মিত আয় হোক, আর পরিবার ছেড়ে শহরে থাকা মানুষ হোটেলের বদলে ঘরের রান্না খেতে পারেন — এই সমস্যার সমাধানই আমাদের কাছে লাভের চেয়ে বড়। কাজটা ঠিকভাবে করতে প্রথমে বাড্ডায় শুরু করেছি; সময়মতো খাবার পৌঁছানো আর টাকার স্বচ্ছ হিসাব গুছিয়ে এক এক করে প্রতিটা এলাকায় আসব, তাড়াহুড়ো করে তাঁকে কষ্টে ফেলতে চাই না। তাঁর নাম তালিকায় যত্ন করে রাখা আছে; তাঁর এলাকায় চালু হলেই টিম নিজে যোগাযোগ করবে। তাঁর দোয়া আর আমাদের সৎ চেষ্টায় ইনশাআল্লাহ শিগগিরই তাঁর কাছে আসছি। কোনো তারিখ বলবেন না।',
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
