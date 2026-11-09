<?php

namespace App\Filament\Support;

use App\Models\Area;
use App\Services\Feed\PostReach;
use Filament\Forms\Components\Select;

/** F35: the two "widen the reach" inputs, shared by the kitchen form and the post action. */
class ReachFields
{
    /** @return list<Select> */
    public static function make(bool $relationship = false): array
    {
        $areas = Select::make($relationship ? 'reachAreas' : 'reach_area_ids')
            ->label('এই এলাকাগুলোতেও দেখাবে')
            ->multiple()
            ->helperText('বাছাই করা এলাকার সব ক্রেতা দেখবেন, দূরত্ব যত বেশিই হোক।');

        $areas = $relationship
            ? $areas->relationship('reachAreas', 'name', fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))->preload()
            : $areas->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id')->all());

        return [
            Select::make('reach_m')
                ->label('পরিধি')
                ->options(PostReach::REACH_OPTIONS)
                ->placeholder('স্বাভাবিক — ক্রেতা নিজে দূরত্ব বাছেন (সর্বোচ্চ ৮ কিমি)')
                ->helperText('এই দূরত্বের মধ্যে থাকা সব ক্রেতা দেখবেন, তাঁরা ছোট দূরত্ব বাছলেও।'),
            $areas,
        ];
    }
}
