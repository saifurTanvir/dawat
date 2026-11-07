<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'label' => 'মসজিদের নোটিশ বোর্ড',
            'is_active' => true,
        ];
    }
}
