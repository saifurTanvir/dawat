<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F85: a regular customer who does not want her cook's food today can pick
 * another cook's food nearby. The choice remembers the post and the box.
 * The bot learns it too (CLAUDE.md: bot_facts change with the feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_choices', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->after('kitchen_id')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('post_id')->constrained()->nullOnDelete();
        });

        DB::table('bot_facts')->insert([
            'topic' => 'নিয়মিত গ্রাহক আজকের রান্না পছন্দ না করলে',
            'body' => 'নিয়মিত গ্রাহক সকালে অ্যাপে দেখেন তাঁর গৃহিণী আজ কী রাঁধছেন। পছন্দ হলে কিছু করতে হয় না, খাবার আসবে। পছন্দ না হলে "এই খাবার খাব না" চেপে দুটো কাজের একটা করতে পারেন: (১) কী খেতে চান লিখে দেবেন — শাহিদার আর দাওয়াত টিম ফোন করে ব্যবস্থা করবেন; অথবা (২) "এলাকার অন্য খাবার দেখুন" — আশেপাশের অন্য গৃহিণীরা আজ কী রাঁধছেন তা খুঁজে (নাম, ধরন, দাম, কাছে-দূরে দিয়ে ফিল্টার করে) একটা বেছে নেবেন; সেটাই সেদিন একই শাহিদার পৌঁছে দেবেন, দাম সেই খাবারের। যেকোনো ক্ষেত্রেই নিজের গৃহিণী কিছু জানবেন না।',
            'audience' => 'customer',
            'feature' => 'F85',
            'is_active' => true,
            'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F85')->delete();
        Schema::table('meal_choices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('post_id');
        });
    }
};
