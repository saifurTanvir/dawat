<?php

namespace App\Support\Geo;

/**
 * F31: where Dawat works — the Badda side of Dhaka. Centres are approximate;
 * the admin panel can correct them.
 */
final class ServiceAreas
{
    /** @var list<array{0: string, 1: string, 2: float, 3: float, 4: int}> slug, name, lat, lng, radius in metres */
    public const LIST = [
        ['uttar-badda', 'উত্তর বাড্ডা', 23.7867, 90.4262, 900],
        ['madhya-badda', 'মধ্য বাড্ডা', 23.7805, 90.4256, 800],
        ['dakkhin-badda', 'দক্ষিণ বাড্ডা', 23.7755, 90.4258, 800],
        ['merul-badda', 'মেরুল বাড্ডা', 23.7718, 90.4302, 900],
        ['shahjadpur', 'শাহজাদপুর', 23.7925, 90.4238, 800],
        ['satarkul', 'সাতারকুল', 23.7948, 90.4425, 1200],
        ['bashundhara', 'বসুন্ধরা আবাসিক এলাকা', 23.8190, 90.4355, 1800],
        ['rampura', 'রামপুরা', 23.7620, 90.4200, 1200],
        ['gulshan-1', 'গুলশান ১', 23.7806, 90.4167, 900],
        ['gulshan-2', 'গুলশান ২', 23.7946, 90.4143, 900],
        ['banani', 'বনানী', 23.7937, 90.4045, 1000],
        ['mohakhali', 'মহাখালী', 23.7780, 90.3985, 1000],
    ];
}
