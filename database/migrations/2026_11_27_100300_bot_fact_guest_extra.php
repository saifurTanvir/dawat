<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F64: "পারব" for guest plates always makes a box; a cook who cooked more can add plates to her post. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_facts')->insert([
            'topic' => 'অতিথির খাবারে "পারব" আর পোস্টে বাড়তি প্লেট',
            'body' => 'নিয়মিত গ্রাহক অতিথির জন্য বাড়তি প্লেট চাইলে গৃহিণী অ্যাপে "পারব" চাপলেই বক্স বুক হয় — তাঁর আজকের পোস্টের প্লেট শেষ হয়ে গেলেও (তখন বাড়তি রান্না ধরা হয়, দাম পোস্টের মতোই)। পোস্ট না দিয়ে থাকলে পোস্ট দেওয়ার সাথে বুক হয়; আধা ঘণ্টায় না হলে এলাকা ম্যানেজার ফোন করেন। গৃহিণী বেশি রাঁধলে "আমার পোস্ট"-এ + চেপে প্লেট বাড়াতে পারেন, পোস্ট শেষ হয়ে গেলেও।',
            'audience' => 'cook', 'feature' => 'F64', 'is_active' => true,
            'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F64')->where('topic', 'অতিথির খাবারে "পারব" আর পোস্টে বাড়তি প্লেট')->delete();
    }
};
