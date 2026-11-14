<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Support\FeeFields;
use App\Models\AppSetting;
use App\Models\Area;
use App\Models\Kitchen;
use App\Services\Money\CookFees;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** F56: what comes off a cook's price — for everyone, and who has their own. */
class CookFeesPage extends Page
{
    protected string $view = 'filament.pages.cook-fees';

    protected static ?string $slug = 'cook-fees';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'গৃহিণীর ফি ও কাটা';

    protected static ?string $title = 'গৃহিণীর দাম থেকে কাটা';

    protected static ?int $navigationSort = 5;

    protected function getHeaderActions(): array
    {
        return [$this->editAction()];
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('সবার জন্য নিয়ম বদলান')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalDescription('যে এলাকা বা গৃহিণীর নিজের নিয়ম নেই, তাদের সবার জন্য। নতুন বক্স থেকে লাগবে; আগের বক্সের হিসাব বদলাবে না।')
            ->fillForm(fn () => AppSetting::get(CookFees::KEY))
            ->schema([
                FeeFields::part('platform_fee', CookFees::PART_LABELS['platform'], inherit: false),
                FeeFields::part('delivery_cut', CookFees::PART_LABELS['delivery'], inherit: false),
            ])
            ->action(function (array $data) {
                AppSetting::put(CookFees::KEY, [
                    'platform_fee_type' => $data['platform_fee_type'],
                    'platform_fee_value' => (int) $data['platform_fee_value'],
                    'delivery_cut_type' => $data['delivery_cut_type'],
                    'delivery_cut_value' => (int) $data['delivery_cut_value'],
                ], auth()->user());
                Notification::make()->title('নিয়ম রাখা হয়েছে')->success()->send();
            });
    }

    protected function getViewData(): array
    {
        $fees = app(CookFees::class);
        $custom = fn ($query) => $query->whereNotNull('platform_fee_type')->orWhereNotNull('delivery_cut_type');

        return [
            'global' => $fees->rules(null),
            'example' => $fees->breakdown(null, 10000),
            'areas' => Area::where($custom)->orderBy('sort_order')->get()->map(fn (Area $a) => [
                'name' => $a->name,
                'rules' => self::own($a),
            ]),
            'kitchens' => Kitchen::where($custom)->with('area')->orderBy('display_name')->get()->map(fn (Kitchen $k) => [
                'name' => $k->display_name,
                'area' => $k->area?->name,
                'rules' => self::own($k),
                'example' => $fees->breakdown($k, 10000),
                'url' => KitchenResource::getUrl('edit', ['record' => $k]),
            ]),
            'labels' => CookFees::PART_LABELS,
        ];
    }

    /** The parts this area or cook sets itself; the rest follow the level above. */
    private static function own(Area|Kitchen $model): array
    {
        return collect(CookFees::PARTS)->map(fn ($column) => $model->{$column.'_type'}
            ? CookFees::ruleText(['type' => $model->{$column.'_type'}, 'value' => (int) $model->{$column.'_value'}])
            : 'ওপরের নিয়ম')->all();
    }
}
