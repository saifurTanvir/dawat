<?php

namespace App\Filament\Actions;

use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Filament\Resources\Posts\PostResource;
use App\Http\Controllers\Api\V1\My\MyMenuController;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Services\Images\UnreadablePhotoException;
use App\Services\Orders\BookingPlanner;
use App\Services\Posts\PostForCook;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** F43: the Dawat team posts for a cook, and sets her daily menus. */
final class PostForCookActions
{
    public static function postFor(): Action
    {
        return Action::make('postFor')
            ->label('গৃহিণীর হয়ে পোস্ট দিন')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('success')
            ->visible(fn (Kitchen $record) => $record->status === KitchenStatus::Approved)
            ->modalDescription('গৃহিণী ফোনে যা বললেন তা লিখুন। নিয়মিত গ্রাহকদের বক্স সাথে সাথে বুক হবে, গৃহিণী নোটিফিকেশন পাবেন। ছবি না দিলে তাঁর আগের পোস্টের ছবি ব্যবহার হবে।')
            ->fillForm(fn (Kitchen $record) => [
                'meal_slot' => MealSlot::Lunch->value,
                'available_hours' => 3,
                'plates_total' => max(1, app(BookingPlanner::class)->bookedPlates($record, MealSlot::Lunch)),
            ] + (($m = $record->menus()->first()) ? ['title' => $m->title, 'price_taka' => intdiv($m->price_paisa, 100)] : []))
            ->schema([
                Select::make('meal_slot')->label('কোন বেলা')->options(MealSlot::options())->required()->native(false),
                TextInput::make('title')->label('খাবার')->required()->minLength(2)->maxLength(100)->placeholder('ভাত, মুরগির ঝোল, ডাল'),
                TextInput::make('price_taka')->label('প্লেটের দাম (৳)')->numeric()->required()->minValue(20)->maxValue(2000),
                TextInput::make('plates_total')->label('কয় প্লেট')->numeric()->required()->minValue(1)->maxValue(20)
                    ->helperText('নিয়মিত গ্রাহকদের প্লেট আগে যাবে, বাকিটা ফিডে।'),
                Select::make('available_hours')->label('কতক্ষণ পাওয়া যাবে')->options(collect(range(1, 6))->mapWithKeys(fn ($h) => [$h => $h.' ঘণ্টা']))->required()->native(false),
                Textarea::make('note')->label('নোট (ইচ্ছা হলে)')->maxLength(300)->rows(2),
                FileUpload::make('images')->label('ছবি (ইচ্ছা হলে)')->image()->multiple()->maxFiles(4)->maxSize(12288)->storeFiles(false),
            ])
            ->action(function (Kitchen $record, array $data, Action $action, $livewire) {
                try {
                    $post = app(PostForCook::class)->post($record, $data, array_values($data['images'] ?? []), 'admin', auth()->user());
                } catch (ValidationException|UnreadablePhotoException $e) {
                    $msg = $e instanceof ValidationException ? collect($e->errors())->flatten()->implode(' ') : $e->getMessage();
                    Notification::make()->title('পোস্ট হয়নি')->body($msg)->danger()->persistent()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title('পোস্ট হয়েছে: '.$post->title)
                    ->body('প্লেট বাকি '.$post->plates_left.'/'.$post->plates_total)->success()->send();
                $livewire->redirect(PostResource::getUrl('view', ['record' => $post]));
            });
    }

    public static function menus(): Action
    {
        return Action::make('menus')
            ->label('প্রতিদিনের মেনু')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->modalWidth('4xl')
            ->modalDescription('প্রতিটা বেলার জন্য একটা মেনু। ঠিক করা সময়ে রোজ নিজে পোস্ট হবে, যদি না গৃহিণী নিজে আগে পোস্ট দেন বা "আজ রান্না নেই" বলেন।')
            ->fillForm(fn (Kitchen $record) => ['menus' => $record->menus()->get()->map(fn (KitchenMenu $m) => [
                'meal_slot' => $m->meal_slot->value,
                'title' => $m->title,
                'price_taka' => intdiv($m->price_paisa, 100),
                'plates_total' => $m->plates_total,
                'available_hours' => $m->available_hours,
                'post_at' => $m->postAt(),
                'weekdays' => array_map('strval', $m->weekdays),
                'note' => $m->note,
                'active' => $m->active,
            ])->all()])
            ->schema([
                Repeater::make('menus')->hiddenLabel()->addActionLabel('বেলা যোগ করুন')->maxItems(4)->columns(4)->defaultItems(0)
                    ->schema([
                        Select::make('meal_slot')->label('বেলা')->options(MealSlot::options())->required()->native(false)->distinct(),
                        TextInput::make('title')->label('খাবার')->required()->maxLength(100)->columnSpan(3),
                        TextInput::make('price_taka')->label('দাম (৳)')->numeric()->required()->minValue(20)->maxValue(2000),
                        TextInput::make('plates_total')->label('প্লেট')->numeric()->required()->minValue(1)->maxValue(20),
                        TextInput::make('post_at')->label('কখন পোস্ট হবে')->type('time')->required()->default('10:00')->regex('/^([01]\d|2[0-3]):[0-5]\d(:\d\d)?$/')
                            ->helperText('ঢাকার সময়, যেমন ১০:০০'),
                        Select::make('available_hours')->label('কতক্ষণ')->options(collect(range(1, 6))->mapWithKeys(fn ($h) => [$h => $h.' ঘণ্টা']))->default(3)->required()->native(false),
                        CheckboxList::make('weekdays')->label('কোন দিন')->options(array_combine(array_map('strval', Customer::ALL_WEEKDAYS), Customer::WEEKDAY_LABELS))
                            ->default(array_map('strval', Customer::ALL_WEEKDAYS))->required()->columns(7)->columnSpan(3),
                        Toggle::make('active')->label('চালু')->default(true)->inline(false),
                        Textarea::make('note')->label('নোট')->maxLength(300)->rows(1)->columnSpanFull(),
                    ]),
            ])
            ->action(function (Kitchen $record, array $data) {
                DB::transaction(function () use ($record, $data) {
                    $keep = [];
                    foreach ($data['menus'] ?? [] as $row) {
                        $row['post_at'] = substr((string) $row['post_at'], 0, 5);
                        $valid = Validator::make($row, [
                            'post_at' => ['required', 'date_format:H:i'],
                            'weekdays' => ['required', 'array', 'min:1'],
                        ])->validate();
                        KitchenMenu::updateOrCreate(
                            ['kitchen_id' => $record->id, 'meal_slot' => $row['meal_slot']],
                            MyMenuController::attributes($row + $valid),
                        );
                        $keep[] = $row['meal_slot'];
                    }
                    KitchenMenu::where('kitchen_id', $record->id)->whereNotIn('meal_slot', $keep)->delete();
                });
                Notification::make()->title('প্রতিদিনের মেনু রাখা হয়েছে')->success()->send();
            });
    }
}
