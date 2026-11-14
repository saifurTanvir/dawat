<?php

namespace App\Filament\Support;

use App\Services\Money\CookFees;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * F56: the two cuts from a cook's price (Dawat's fee, delivery share) as form
 * fields. Stored values are paisa (fixed) or hundredths of a percent; both show
 * as value / 100, so ৳১০ and ১০% are typed as 10.
 */
class FeeFields
{
    /** @param  bool  $inherit  areas and cooks may leave a part to the next level */
    public static function section(string $description, bool $inherit = true): Section
    {
        return Section::make('গৃহিণীর দাম থেকে কাটা')
            ->description($description)
            ->collapsible()
            ->schema([
                self::part('platform_fee', CookFees::PART_LABELS['platform'], $inherit),
                self::part('delivery_cut', CookFees::PART_LABELS['delivery'], $inherit),
            ]);
    }

    public static function part(string $column, string $label, bool $inherit = true): Grid
    {
        $types = $inherit ? ['' => 'ওপরের নিয়ম মেনে', ...CookFees::TYPES] : CookFees::TYPES;

        return Grid::make(2)->schema([
            Select::make($column.'_type')->label($label)->options($types)->live()
                ->selectablePlaceholder(false)
                ->default($inherit ? '' : 'fixed')
                ->formatStateUsing(fn ($state) => $state ?? '')
                ->dehydrateStateUsing(fn ($state) => $state ?: null),
            TextInput::make($column.'_value')
                ->label(fn (Get $get) => $get($column.'_type') === 'percent' ? 'কত শতাংশ (%)' : 'প্রতি প্লেট কত টাকা (৳)')
                ->numeric()->minValue(0)
                ->maxValue(fn (Get $get) => $get($column.'_type') === 'percent' ? 50 : 500)
                ->required(fn (Get $get) => filled($get($column.'_type')))
                ->visible(fn (Get $get) => filled($get($column.'_type')))
                ->afterStateHydrated(fn ($component, $state) => $component->state($state === null ? null : $state / 100))
                ->dehydrateStateUsing(fn ($state, Get $get) => filled($get($column.'_type')) && $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null),
        ]);
    }
}
