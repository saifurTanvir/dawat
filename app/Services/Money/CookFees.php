<?php

namespace App\Services\Money;

use App\Models\AppSetting;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Order;

/**
 * F56: what comes off a cook's price before she is paid.
 *
 * Two parts, each set for everyone, then per area, then per cook (the most
 * specific one wins, part by part):
 * - platform: Dawat's fee
 * - delivery: the cook's share of getting the box to the buyer
 *
 * Each is either a fixed amount per plate ("fixed", value in paisa) or a share
 * of the price ("percent", value in hundredths of a percent). Nothing comes off
 * when the buyer collects from the cook herself — she takes the cash.
 */
class CookFees
{
    public const KEY = 'cook_fees';

    public const PARTS = ['platform' => 'platform_fee', 'delivery' => 'delivery_cut'];

    public const TYPES = ['fixed' => 'প্রতি প্লেট নির্দিষ্ট টাকা', 'percent' => 'দামের শতকরা (%)'];

    public const PART_LABELS = ['platform' => 'দাওয়াতের ফি', 'delivery' => 'ডেলিভারি খরচ'];

    public const SOURCE_LABELS = ['global' => 'সবার জন্য', 'area' => 'এলাকার', 'kitchen' => 'এই গৃহিণীর'];

    /**
     * The rule in force for each part, and where it came from.
     *
     * @return array{platform: array{type: string, value: int, source: string}, delivery: array{type: string, value: int, source: string}}
     */
    public function rules(?Kitchen $kitchen): array
    {
        $global = AppSetting::get(self::KEY);
        $area = $kitchen?->area_id ? ($kitchen->relationLoaded('area') ? $kitchen->area : Area::find($kitchen->area_id)) : null;

        $out = [];
        foreach (self::PARTS as $part => $column) {
            $out[$part] = match (true) {
                $kitchen && $kitchen->{$column.'_type'} !== null => ['type' => $kitchen->{$column.'_type'}, 'value' => (int) $kitchen->{$column.'_value'}, 'source' => 'kitchen'],
                $area && $area->{$column.'_type'} !== null => ['type' => $area->{$column.'_type'}, 'value' => (int) $area->{$column.'_value'}, 'source' => 'area'],
                default => ['type' => $global[$column.'_type'] ?? 'fixed', 'value' => (int) ($global[$column.'_value'] ?? 0), 'source' => 'global'],
            };
        }

        return $out;
    }

    public static function amount(array $rule, int $foodPaisa, int $plates): int
    {
        return $rule['type'] === 'percent'
            ? (int) round($foodPaisa * $rule['value'] / 10000)
            : $rule['value'] * max(1, $plates);
    }

    /**
     * For a price per plate: what comes off and what the cook gets.
     *
     * @return array{price: int, platform: int, delivery: int, cook: int}
     */
    public function breakdown(?Kitchen $kitchen, int $pricePaisa, int $plates = 1, bool $delivered = true): array
    {
        $food = $pricePaisa * max(1, $plates);
        if (! $delivered) {
            return ['price' => $food, 'platform' => 0, 'delivery' => 0, 'cook' => $food];
        }
        $rules = $this->rules($kitchen);
        $platform = min($food, self::amount($rules['platform'], $food, $plates));
        $delivery = min($food - $platform, self::amount($rules['delivery'], $food, $plates));

        return ['price' => $food, 'platform' => $platform, 'delivery' => $delivery, 'cook' => $food - $platform - $delivery];
    }

    /** Fixes the fees on a box when it is made or its food changes, until the cook is paid. */
    public function stamp(Order $order): void
    {
        if ($order->cook_settlement_id || ! $order->kitchen_id) {
            return;
        }
        $plates = max(1, (int) $order->plates);
        $b = $this->breakdown(
            $order->relationLoaded('kitchen') ? $order->kitchen : Kitchen::find($order->kitchen_id),
            intdiv((int) $order->food_paisa, $plates),
            $plates,
            delivered: $order->payment_method !== 'cash_to_cook' && $order->receive !== 'self',
        );
        // Rounding a per-plate price can lose a paisa; charge on the real total.
        $order->platform_fee_paisa = min((int) $order->food_paisa, $b['platform']);
        $order->cook_delivery_paisa = min((int) $order->food_paisa - $order->platform_fee_paisa, $b['delivery']);
    }

    /** "৳১০" or "১০%" for a rule. */
    public static function ruleText(array $rule): string
    {
        return $rule['type'] === 'percent'
            ? Order::banglaDigits(rtrim(rtrim(number_format($rule['value'] / 100, 2), '0'), '.')).'%'
            : '৳'.Order::banglaDigits(rtrim(rtrim(number_format($rule['value'] / 100, 2), '0'), '.')).' প্রতি প্লেট';
    }

    /** "গৃহিণী পাবেন ৳৮০ (দাওয়াতের ফি ৳১০, ডেলিভারি ৳১০)" for a price in taka. */
    public static function shareLine(?Kitchen $kitchen, int $priceTaka): string
    {
        if ($priceTaka <= 0) {
            return '';
        }
        $b = app(self::class)->breakdown($kitchen, $priceTaka * 100);
        $tk = fn (int $p) => '৳'.Order::banglaDigits(rtrim(rtrim(number_format($p / 100, 2), '0'), '.'));

        return 'প্রতি প্লেটে গৃহিণী পাবেন '.$tk($b['cook']).' (দাওয়াতের ফি '.$tk($b['platform']).', ডেলিভারি খরচ '.$tk($b['delivery']).')।';
    }

    /** What the app needs to show the cook her share as she types a price. */
    public function forApp(?Kitchen $kitchen): array
    {
        $rules = $this->rules($kitchen);

        return collect($rules)->map(fn ($r) => ['type' => $r['type'], 'value' => $r['value'], 'text' => self::ruleText($r)])->all();
    }
}
