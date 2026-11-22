<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F99: the area-manager form asks only name, mobile, where they live and
 * vehicle; the rest is asked on the phone call. Pay is ৳15 a parcel.
 */
return new class extends Migration
{
    private const OPTIONAL = ['gender', 'job', 'has_smartphone', 'has_nid', 'hours', 'slots', 'days_per_week'];

    private const BODY = 'এলাকা ম্যানেজার নিজের এলাকার দায়িত্বে থাকেন: সকালে গৃহিণীরা আজ কী রাঁধবেন আর কোন গ্রাহক আজ খাবেন তা মিলিয়ে নেন, তারপর দুপুরে গৃহিণীর বাসা থেকে বক্স নিয়ে গ্রাহকের বাসা বা অফিসে পৌঁছে দেন। হেঁটে, সাইকেলে বা বাইকে করা যায়; নিজের বাসার এলাকাতেই কাজ। আয়: প্রতিটা পার্সেলে ১৫ টাকা। যেমন দিনে ২০ পার্সেলে ৩০০ টাকা, ৫০ পার্সেলে ৭৫০ টাকা, ১০০ পার্সেলে ১,৫০০ টাকা — মাসে ২৬ দিনে প্রায় ৩৯,০০০ টাকা। শুরুতে পার্সেল কম থাকে, এলাকায় গ্রাহক বাড়লে আয়ও বাড়ে — নির্দিষ্ট আয়ের কথা দেওয়া যাবে না। আবেদন খুব ছোট: dawat.cloud/join/area-manager — শুধু নাম, মোবাইল নম্বর, কোথায় থাকেন আর যানবাহন; বাকি কথা টিম ফোনে জেনে নেবে।';

    public function up(): void
    {
        Schema::table('shahidar_applications', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->change();
            $table->string('job', 20)->nullable()->change();
            $table->boolean('has_smartphone')->nullable()->change();
            $table->boolean('has_nid')->nullable()->change();
            $table->string('hours', 20)->nullable()->change();
            $table->json('slots')->nullable()->change();
            $table->unsignedTinyInteger('days_per_week')->nullable()->change();
        });

        $fact = $this->fact();
        if ($fact) {
            DB::table('bot_facts')->where('id', $fact->id)->update(['body' => self::BODY, 'updated_at' => now()]);
        } else {
            $now = now();
            DB::table('bot_facts')->insert([
                'audience' => 'shahidar', 'feature' => 'F99', 'topic' => 'এলাকা ম্যানেজার হতে চাইলে', 'body' => self::BODY,
                'is_active' => true, 'sort_order' => (int) DB::table('bot_facts')->max('sort_order') + 10,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        cache()->forget('bot-facts:prompt');
    }

    public function down(): void
    {
        // The columns stay nullable: older rows may already have gaps.
        cache()->forget('bot-facts:prompt');
    }

    private function fact(): ?object
    {
        return DB::table('bot_facts')->where('audience', 'shahidar')->where('topic', 'like', '%হতে চাইলে%')->orderBy('id')->first();
    }
};
