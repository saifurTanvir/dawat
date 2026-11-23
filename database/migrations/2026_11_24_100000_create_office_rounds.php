<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F95: office rounds. The area manager collects one office's boxes cook by
 * cook, following the map, and each cook is told before he comes — about 30
 * minutes ahead, and again when he is on his way to her. The office gets a
 * pin so the last stop can be found on the map too.
 * The bot learns it too (CLAUDE.md: bot_facts change with the feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Exact office location; only admins and the office's area manager on a round see it.
            $table->geography('location', subtype: 'point', srid: 4326)->nullable()->after('address');
        });

        Schema::create('round_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_drop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shahidar_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('boxes')->default(0);
            $table->timestamp('started_at')->nullable();   // the round's start, same on every stop
            $table->decimal('start_lat', 9, 6)->nullable(); // where he was when he set out (if the phone said)
            $table->decimal('start_lng', 9, 6)->nullable();
            $table->timestamp('eta_at')->nullable();
            $table->timestamp('soon_sent_at')->nullable(); // "coming in about 30 minutes"
            $table->timestamp('next_sent_at')->nullable(); // "on his way to you now"
            $table->boolean('sms_sent')->default(false);
            $table->string('cook_reply', 10)->nullable();  // ready | late
            $table->timestamp('cook_ready_at')->nullable();
            $table->unsignedTinyInteger('later')->default(0); // "পরে আসব" taps
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();
            $table->unique(['office_drop_id', 'shahidar_id', 'kitchen_id']);
            $table->index(['kitchen_id', 'picked_at']);
        });

        $sort = (int) DB::table('bot_facts')->max('sort_order');
        DB::table('bot_facts')->insert([
            [
                'topic' => 'অফিসের খাবার নিতে এলাকা ম্যানেজার কখন আসবেন',
                'body' => 'অফিসের খাবার এলাকা ম্যানেজার একটা "রাউন্ডে" তোলেন: ম্যাপ ধরে এক গৃহিণীর বাসা থেকে আরেকজনের বাসা, শেষে অফিস। গৃহিণীকে আগে থেকে জানানো হয় — উনি আসার প্রায় ৩০ মিনিট আগে একবার ("আনুমানিক কয়টায় আসবেন, বক্স তৈরি রাখুন"), আর আগের বাসা থেকে খাবার নিয়ে যখন তাঁর দিকে রওনা দেন তখন আরেকবার ("এখন আপনার কাছে আসছেন")। অ্যাপে নোটিফিকেশন যায়; অ্যাপের নোটিফিকেশন চালু না থাকলে SMS যায়। গৃহিণী অ্যাপে "তৈরি আছি" বা "আরও ১০ মিনিট লাগবে" চাপতে পারেন — দ্বিতীয়টা চাপলে এলাকা ম্যানেজার আগে অন্য বাসায় যান। গৃহিণী কোন অফিস বা কোন গ্রাহক তা জানেন না, শুধু কয়টা বক্স।',
                'audience' => 'cook',
                'feature' => 'F95',
                'is_active' => true,
                'sort_order' => $sort + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'এলাকা ম্যানেজারের অফিস রাউন্ড',
                'body' => 'এলাকা ম্যানেজার অ্যাপের আজকের তালিকায় প্রতিটা অফিসের কার্ডে "রাউন্ড শুরু" চাপেন। অ্যাপ একবারে একটা বাসা দেখায় — কোন গৃহিণী, ঠিকানা, কয়টা বক্স — সাথে "পথ দেখান" (Google Maps-এ পথ), কল আর WhatsApp। "রওনা দিচ্ছি" চাপলে প্রথম গৃহিণী খবর পান; প্রতিটা বাসায় "বক্স নিয়েছি" চাপলে পরের বাসা নিজে থেকে আসে আর সেই গৃহিণী খবর পান। কেউ তৈরি না থাকলে "পরে আসব"। সব তোলা হলে অ্যাপ অফিসের পথ দেখায়, সেখানে কোড দিয়ে সব বক্স একবারে বুঝিয়ে দেন।',
                'audience' => 'shahidar',
                'feature' => 'F95',
                'is_active' => true,
                'sort_order' => $sort + 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('bot_facts')->where('feature', 'F95')->delete();
        Schema::dropIfExists('round_stops');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('location'));
    }
};
