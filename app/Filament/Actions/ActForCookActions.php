<?php

namespace App\Filament\Actions;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Services\Kitchens\ActForCook;
use App\Services\Kitchens\CookActions;
use App\Services\Orders\BookingPlanner;
use App\Support\ContactHours;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * F46: the Dawat team does a cook's daily work for her. Each action goes in
 * the kitchen's log and she is notified.
 */
final class ActForCookActions
{
    private static function act(): ActForCook
    {
        return app(ActForCook::class);
    }

    private static function cook(): CookActions
    {
        return app(CookActions::class);
    }

    /** Run, and turn a refusal into a red notice. */
    private static function attempt(Action $action, Closure $do, string $done): void
    {
        try {
            $do();
        } catch (ValidationException $e) {
            Notification::make()->title('হয়নি')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
            $action->halt();

            return;
        }
        Notification::make()->title($done)->body('গৃহিণীকে জানানো হয়েছে।')->success()->send();
    }

    /** Meals that have boxes for a shahidar to collect today. @return array<string, string> */
    private static function readySlots(Kitchen $kitchen): array
    {
        return Order::where('kitchen_id', $kitchen->id)->whereDate('service_date', BookingPlanner::today())
            ->where('status', OrderStatus::Booked)->whereNotNull('shahidar_id')
            ->where(fn ($q) => $q->whereNull('receive')->orWhere('receive', '!=', 'self'))
            ->pluck('meal_slot')->unique()->mapWithKeys(fn (MealSlot $s) => [$s->value => $s->label()])->all();
    }

    public static function ready(): Action
    {
        return Action::make('readyFor')
            ->label('খাবার রেডি জানান')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (Kitchen $record) => self::readySlots($record) !== [])
            ->schema(fn (Kitchen $record) => [
                Select::make('meal_slot')->label('কোন বেলা')->options(self::readySlots($record))->required()->native(false)
                    ->default(array_key_first(self::readySlots($record))),
                TextInput::make('note')->label('শাহিদারের জন্য (ইচ্ছা হলে)')->maxLength(200)->placeholder('যেমন: ২টা পর্যন্ত বাসায়, তারপর দারোয়ানের কাছে'),
            ])
            ->action(function (Kitchen $record, array $data, Action $action) {
                $slot = MealSlot::from($data['meal_slot']);
                self::attempt($action, fn () => self::act()->run($record, auth()->user(), 'ready',
                    "আজ {$slot->label()}: খাবার রেডি জানানো হয়েছে".(filled($data['note'] ?? null) ? " — {$data['note']}" : ''),
                    fn () => self::cook()->ready($record, $slot, $data['note'] ?? null)), 'রেডি জানানো হয়েছে');
            });
    }

    public static function off(): Action
    {
        return Action::make('offFor')
            ->label('আজ রান্না নেই')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalDescription('এই বেলার নিয়মিত গ্রাহকদের জানানো হবে, আজকের মেনুও পোস্ট হবে না।')
            ->schema([
                Select::make('meal_slot')->label('কোন বেলা')->options(MealSlot::options())->required()->native(false),
                TextInput::make('reason')->label('কারণ')->required()->minLength(3)->maxLength(200),
            ])
            ->action(function (Kitchen $record, array $data, Action $action) {
                $slot = MealSlot::from($data['meal_slot']);
                self::attempt($action, fn () => self::act()->run($record, auth()->user(), 'off',
                    "আজ {$slot->label()}: রান্না নেই — {$data['reason']}", fn () => self::cook()->off($record, $slot)), 'লিখে রাখা হয়েছে');
            });
    }

    public static function contactHours(): Action
    {
        return Action::make('contactHoursFor')
            ->label('কখন ফোন করা যাবে')
            ->icon(Heroicon::OutlinedPhone)
            ->fillForm(fn (Kitchen $record) => [
                'from' => ContactHours::hours($record->user)[0],
                'to' => ContactHours::hours($record->user)[1],
                'prefer' => $record->user->contact_prefer ?: 'any',
            ])
            ->schema([
                TextInput::make('from')->label('কখন থেকে')->type('time')->required(),
                TextInput::make('to')->label('কখন পর্যন্ত')->type('time')->required(),
                Radio::make('prefer')->label('কীভাবে যোগাযোগ পছন্দ')->options(ContactHours::PREFER_LABELS)->required(),
            ])
            ->action(function (Kitchen $record, array $data, Action $action) {
                $from = substr($data['from'], 0, 5);
                $to = substr($data['to'], 0, 5);
                self::attempt($action, function () use ($record, $from, $to, $data) {
                    if ($from < config('dawat.contact.earliest') || $to > config('dawat.contact.latest') || $to <= $from) {
                        throw ValidationException::withMessages(['to' => 'সময় সকাল ৭টা থেকে রাত ১০টার মধ্যে, আর শেষ সময় শুরুর পরে হতে হবে।']);
                    }
                    self::act()->run($record, auth()->user(), 'contact', 'কখন ফোন করা যাবে: '.ContactHours::clock($from).' – '.ContactHours::clock($to),
                        fn () => $record->user->forceFill(['call_from' => $from, 'call_to' => $to, 'contact_prefer' => $data['prefer']])->save());
                }, 'রাখা হয়েছে');
            });
    }

    /* ---------- posts ---------- */

    public static function plates(): Action
    {
        return Action::make('platesFor')
            ->label('প্লেট বদলান')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->fillForm(fn (Post $record) => ['plates_left' => $record->plates_left])
            ->schema([TextInput::make('plates_left')->label('ফিডে কয় প্লেট বাকি')->numeric()->required()->minValue(0)->maxValue(20)
                ->helperText('নিয়মিত গ্রাহকদের প্লেট এর বাইরে।')])
            ->action(function (Post $record, array $data, Action $action) {
                self::attempt($action, fn () => self::act()->run($record->kitchen, auth()->user(), 'plates',
                    "{$record->meal_slot->label()} পোস্ট \"{$record->title}\": ফিডে বাকি ".Order::banglaDigits((string) $data['plates_left']).' প্লেট',
                    fn () => self::cook()->setPlates($record, (int) $data['plates_left']), post: $record), 'বদলানো হয়েছে');
            });
    }

    public static function edit(): Action
    {
        return Action::make('editFor')
            ->label('নাম/নোট/দাম ঠিক করুন')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->fillForm(fn (Post $record) => ['title' => $record->title, 'note' => $record->note, 'price_taka' => intdiv($record->price_paisa, 100)])
            ->schema(fn (Post $record) => [
                TextInput::make('title')->label('খাবার')->required()->minLength(2)->maxLength(100),
                Textarea::make('note')->label('নোট')->maxLength(300)->rows(2),
                TextInput::make('price_taka')->label('প্লেটের দাম (৳)')->numeric()->required()->minValue(20)->maxValue(2000)
                    ->disabled(fn () => $record->orders()->where('status', '!=', OrderStatus::Cancelled)->exists())
                    ->helperText(fn () => $record->orders()->where('status', '!=', OrderStatus::Cancelled)->exists()
                        ? 'এই পোস্ট থেকে বক্স বুক হয়ে গেছে, তাই দাম বদলানো যাবে না।' : 'কারো বক্স বুক হওয়ার আগে পর্যন্তই দাম বদলানো যায়।'),
            ])
            ->action(function (Post $record, array $data, Action $action) {
                $price = isset($data['price_taka']) ? (int) $data['price_taka'] * 100 : null;
                $details = "{$record->meal_slot->label()} পোস্ট ঠিক করা হয়েছে: {$data['title']}"
                    .($price !== null && $price !== $record->price_paisa ? ', দাম ৳'.Order::banglaDigits((string) intdiv($price, 100)) : '');
                self::attempt($action, fn () => self::act()->run($record->kitchen, auth()->user(), 'edit', $details,
                    fn () => self::cook()->editPost($record, $data['title'], $data['note'] ?? null, $price), post: $record), 'ঠিক করা হয়েছে');
            });
    }

    public static function soldOut(): Action
    {
        return Action::make('soldOutFor')
            ->label('শেষ হয়ে গেছে')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->visible(fn (Post $record) => $record->plates_left > 0)
            ->requiresConfirmation()
            ->action(function (Post $record, Action $action) {
                self::attempt($action, fn () => self::act()->run($record->kitchen, auth()->user(), 'sold_out',
                    "{$record->meal_slot->label()} পোস্ট \"{$record->title}\": শেষ হয়ে গেছে", fn () => self::cook()->soldOut($record), post: $record), 'শেষ লেখা হয়েছে');
            });
    }

    /* ---------- one-off orders ---------- */

    public static function decline(): Action
    {
        return Action::make('declineFor')
            ->label('দিতে পারছি না')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record) => $record->isOneOff() && $record->status === OrderStatus::Booked)
            ->requiresConfirmation()
            ->modalDescription('গৃহিণী এই অর্ডার দিতে পারছেন না। ক্রেতাকে জানানো হবে, প্লেট ফিডে ফিরবে।')
            ->action(function (Order $record, Action $action) {
                self::attempt($action, fn () => self::act()->run($record->kitchen, auth()->user(), 'decline',
                    "ফিডের অর্ডার বক্স {$record->code()}: দেওয়া যাচ্ছে না", fn () => self::cook()->decline($record, timeLimit: false), order: $record), 'বাতিল হয়েছে');
            });
    }

    public static function handed(): Action
    {
        return Action::make('handedFor')
            ->label('ক্রেতা নিয়েছেন')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('success')
            ->visible(fn (Order $record) => $record->isSelfPickup() && $record->status === OrderStatus::Booked)
            ->requiresConfirmation()
            ->modalDescription('ক্রেতা রান্নাঘর থেকে খাবার নিয়েছেন আর গৃহিণীকে টাকা দিয়েছেন।')
            ->action(function (Order $record, Action $action) {
                self::attempt($action, fn () => self::act()->run($record->kitchen, auth()->user(), 'handed',
                    "বক্স {$record->code()}: ক্রেতা রান্নাঘর থেকে নিয়েছেন", fn () => self::cook()->handed($record, auth()->user()), order: $record), 'লিখে রাখা হয়েছে');
            });
    }
}
