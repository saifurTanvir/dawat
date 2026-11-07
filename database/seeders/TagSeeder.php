<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/** The tap-to-select tags a cook can add to a post. Admins can add more later. */
class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['halal', 'হালাল', TagType::Diet],
            ['vegetarian', 'নিরামিষ', TagType::Diet],
            ['diabetic-friendly', 'ডায়াবেটিক-বান্ধব', TagType::Diet],
            ['kid-friendly', 'শিশুদের উপযোগী', TagType::Diet],
            ['spice-low', 'ঝাল কম', TagType::Spice],
            ['spice-medium', 'ঝাল মাঝারি', TagType::Spice],
            ['spice-high', 'ঝাল বেশি', TagType::Spice],
            ['oil-low', 'তেল কম', TagType::Oil],
            ['oil-high', 'তেল বেশি', TagType::Oil],
            ['salt-low', 'লবণ কম', TagType::Warning],
            ['turmeric-high', 'হলুদ বেশি', TagType::Warning],
            ['contains-nuts', 'বাদাম আছে', TagType::Warning],
            ['contains-shrimp', 'চিংড়ি আছে', TagType::Warning],
        ];

        foreach ($tags as $order => [$slug, $name, $type]) {
            Tag::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'type' => $type, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }
}
