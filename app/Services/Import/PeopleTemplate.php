<?php

namespace App\Services\Import;

use App\Models\Area;
use App\Models\PeopleImport;
use App\Models\PeopleImportRow;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** F59: the Excel template admins fill in, and the result file after a run. */
class PeopleTemplate
{
    /** Header, who it is for, what to write, width. */
    public const COLUMNS = [
        ['মোবাইল', 'সবার', 'আবশ্যক। 017XXXXXXXX, ০১৭…, +880… সব চলবে।', 16],
        ['নাম', 'সবার', 'আবশ্যক।', 20],
        ['ভূমিকা', 'সবার', 'আবশ্যক: গ্রাহক / গৃহিণী / শাহিদার।', 11],
        ['এলাকা', 'সবার', 'আবশ্যক। "এলাকার নাম" শিটের নাম হুবহু। শাহিদারের একাধিক এলাকা কমা দিয়ে।', 24],
        ['ঠিকানা', 'গ্রাহক, গৃহিণী', 'গ্রাহক: ডেলিভারির ঠিকানা (আবশ্যক)। গৃহিণী: পিকআপের ঠিকানা (ইচ্ছা হলে)।', 30],
        ['রান্নাঘরের নাম', 'গৃহিণী', 'ইচ্ছা হলে। খালি থাকলে "<নাম>-এর রান্নাঘর"।', 22],
        ['লোকেশন লিংক', 'গ্রাহক, গৃহিণী', 'ইচ্ছা হলে। গুগল ম্যাপ/হোয়াটসঅ্যাপ লিংক বা "23.78, 90.42"। শুধু অ্যাডমিন দেখবেন।', 26],
        ['বেলা', 'গ্রাহক', 'ইচ্ছা হলে, কমা দিয়ে: সকাল, দুপুর, বিকেল, রাত। খালি = দুপুর।', 14],
        ['প্লেট', 'গ্রাহক', 'ইচ্ছা হলে, ১–১০। খালি = ১।', 8],
        ['দিন', 'গ্রাহক', 'ইচ্ছা হলে, কমা দিয়ে: রবি, সোম, মঙ্গল, বুধ, বৃহস্পতি, শুক্র, শনি। খালি = প্রতিদিন।', 22],
        ['ক্যাম্পেইন', 'সবার', 'ইচ্ছা হলে। কোন বিজ্ঞাপন থেকে, যেমন "ফেসবুক নভেম্বর লাঞ্চ"।', 20],
        ['নোট', 'সবার', 'ইচ্ছা হলে। যেমন: ঝাল কম, বিকেলে ফোন দিন।', 24],
    ];

    public function template(): string
    {
        $areas = Area::active()->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
        $a1 = $areas[0] ?? 'উত্তর বাড্ডা';
        $a2 = $areas[1] ?? $a1;
        $examples = [
            ['01711000001', 'জামান সাহেব', 'গ্রাহক', $a1, 'বাসা ১২, রোড ৩, ফ্ল্যাট ৪বি', '', 'https://maps.google.com/?q=23.7801,90.4253', 'দুপুর', '2', 'রবি, সোম, মঙ্গল, বুধ, বৃহস্পতি', 'ফেসবুক নভেম্বর লাঞ্চ', 'ঝাল কম'],
            ['01811000002', 'রহিমা বেগম', 'গৃহিণী', $a1, 'বাসা ৫, গলি ২', 'রহিমা খালার রান্নাঘর', '', '', '', '', 'ফেসবুক গৃহিণী', 'বিকেলে ফোন দিন'],
            ['01911000003', 'করিম মিয়া', 'শাহিদার', $a1 === $a2 ? $a1 : "{$a1}, {$a2}", '', '', '', '', '', '', 'শাহিদার আবেদন', ''],
        ];

        $options = new Options;
        foreach (self::COLUMNS as $i => $c) {
            $options->setColumnWidth($c[3], $i + 1);
        }
        $path = tempnam(sys_get_temp_dir(), 'dawat-template').'.xlsx';
        $writer = new Writer($options);
        $writer->openToFile($path);
        $head = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1E5B3F');
        $bold = (new Style)->setFontBold();

        $writer->getCurrentSheet()->setName('তালিকা');
        $writer->addRow(Row::fromValues(array_column(self::COLUMNS, 0), $head));
        foreach ($examples as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('এলাকার নাম');
        $writer->addRow(Row::fromValues(['এলাকা (এই নামগুলোই লিখুন)'], $bold));
        foreach ($areas as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('নির্দেশনা');
        foreach ([
            'দাওয়াতে তালিকা থেকে একসাথে যোগ করা',
            '১. "তালিকা" শিটে প্রতি লাইনে একজন। উদাহরণের ৩টা লাইন মুছে নিজের তালিকা বসান।',
            '২. মোবাইল, নাম, ভূমিকা আর এলাকা সবার জন্য আবশ্যক; গ্রাহকের জন্য ঠিকানাও। বাকিগুলো ইচ্ছা হলে।',
            '৩. আপলোডের পর আগে দেখাবে কোন লাইনে কী হবে। "যোগ করুন" চাপলে তবেই যোগ হবে।',
            '৪. কাউকে SMS যাবে না। গৃহিণীরা "অনুমোদনের অপেক্ষায়" থাকবেন, ফোনে কথা বলে অনুমোদন দিন।',
            '৫. আগে থেকে থাকা কারও নাম-ঠিকানা বদলাবে না, শুধু নতুন ভূমিকা যোগ হবে।',
            '৬. CSV-ও চলবে (UTF-8), প্রথম লাইনে একই কলামের নাম। একবারে সর্বোচ্চ '.PeopleSheet::MAX_ROWS.' জন।',
        ] as $i => $line) {
            $writer->addRow(Row::fromValues([$line], $i === 0 ? $bold : null));
        }
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['কলাম', 'কার জন্য', 'কী লিখবেন'], $bold));
        foreach (self::COLUMNS as $c) {
            $writer->addRow(Row::fromValues([$c[0], $c[1], $c[2]]));
        }
        $writer->close();

        return $path;
    }

    /** CSV of every line and what happened, so errors can be fixed and uploaded again. */
    public function results(PeopleImport $import): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dawat-result');
        $out = fopen($path, 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['লাইন', 'মোবাইল', 'নাম', 'ভূমিকা', 'যা হবে', 'যা হয়েছে', 'কারণ / নোট']);
        foreach ($import->rows()->get() as $row) {
            fputcsv($out, [
                $row->line, $row->phone, $row->name,
                PeopleImportRow::ROLES[$row->role] ?? '',
                PeopleImportRow::PLANS[$row->plan] ?? $row->plan,
                $row->result ? (PeopleImportRow::RESULTS[$row->result] ?? $row->result) : '',
                $row->message,
            ]);
        }
        fclose($out);

        return $path;
    }
}
