<?php

namespace App\Filament\Actions;

use App\Actions\EnsurePerson;
use App\Enums\KitchenStatus;
use App\Filament\Pages\ImportPeople;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Filament\Support\PhoneField;
use App\Models\Area;
use App\Models\Kitchen;
use App\Support\Geo\MapLink;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F58: add a customer, a cook or a shahidar from anywhere — the dashboard's top
 * buttons. Each form's text boxes get a microphone for Bangla voice typing.
 */
final class QuickAddActions
{
    /** Marks a modal so resources/views/filament/voice-input.blade.php adds mic buttons. */
    private const VOICE = ['data-voice-form' => 'bn-BD'];

    public static function all(): array
    {
        return [self::customer(), self::cook(), self::shahidar(), self::fromList()];
    }

    /** F59: many at once, from a marketing list. */
    public static function fromList(): Action
    {
        return Action::make('fromList')
            ->label('তালিকা থেকে যোগ করুন')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->url(ImportPeople::getUrl());
    }

    public static function customer(): Action
    {
        return Action::make('addCustomer')
            ->label('গ্রাহক যোগ করুন')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->modalHeading('নতুন নিয়মিত গ্রাহক')
            ->modalSubmitActionLabel('যোগ করুন')
            ->extraModalWindowAttributes(self::VOICE)
            ->schema(CustomerResource::createFields())
            ->action(function (array $data, $livewire) {
                $customer = CustomerResource::createCustomer($data);
                Notification::make()->title('গ্রাহক যোগ হয়েছে: '.$customer->user->name)->success()->send();
                $livewire->redirect(CustomerResource::getUrl('view', ['record' => $customer]));
            });
    }

    public static function shahidar(): Action
    {
        return Action::make('addShahidar')
            ->label('শাহিদার যোগ করুন')
            ->icon(Heroicon::OutlinedTruck)
            ->color('info')
            ->modalHeading('নতুন শাহিদার')
            ->modalSubmitActionLabel('যোগ করুন')
            ->extraModalWindowAttributes(self::VOICE)
            ->schema(ShahidarResource::createForm())
            ->action(function (array $data, $livewire) {
                $user = ShahidarResource::createShahidar($data);
                Notification::make()->title('শাহিদার যোগ হয়েছে: '.$user->name)->success()->send();
                $livewire->redirect(ShahidarResource::getUrl('view', ['record' => $user]));
            });
    }

    public static function cook(): Action
    {
        return Action::make('addCook')
            ->label('রাঁধুনি যোগ করুন')
            ->icon(Heroicon::OutlinedFire)
            ->color('warning')
            ->modalHeading('নতুন রাঁধুনি (গৃহিণী)')
            ->modalDescription('এই নম্বরে OTP দিয়ে গৃহিণী নিজের অ্যাপে ঢুকতে পারবেন। বাসার লোকেশন না দিলে গৃহিণী অ্যাপ থেকে ম্যাপে পিন দেবেন, তারপর অনুমোদন দিন।')
            ->modalSubmitActionLabel('যোগ করুন')
            ->extraModalWindowAttributes(self::VOICE)
            ->schema(self::cookFields())
            ->action(function (array $data, $livewire) {
                $kitchen = self::createCook($data);
                Notification::make()
                    ->title('রাঁধুনি যোগ হয়েছে: '.$kitchen->display_name)
                    ->body($kitchen->isApproved() ? 'অনুমোদনও দেওয়া হয়েছে।' : 'অনুমোদনের অপেক্ষায়।')
                    ->success()->send();
                $livewire->redirect(KitchenResource::getUrl('view', ['record' => $kitchen]));
            });
    }

    public static function cookFields(): array
    {
        return [
            PhoneField::make()->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                $phone = PhoneNumber::normalize((string) $value);
                if ($phone && Kitchen::whereHas('user', fn ($query) => $query->where('phone', $phone))->exists()) {
                    $fail('এই নম্বরে আগে থেকেই একটা রান্নাঘর আছে।');
                }
            }),
            TextInput::make('name')->label('গৃহিণীর নাম')->required()->maxLength(60),
            TextInput::make('display_name')->label('রান্নাঘরের নাম')->required()->minLength(2)->maxLength(60)
                ->placeholder('যেমন: রহিমা খালার রান্নাঘর'),
            Select::make('area_id')->label('এলাকা')->required()
                ->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id')),
            TextInput::make('pickup_address')->label('পিকআপের ঠিকানা')->maxLength(255)
                ->helperText('শাহিদার এখান থেকে খাবার তুলবেন। ক্রেতারা কখনো দেখেন না।'),
            TextInput::make('where')->label('বাসার লোকেশন (ইচ্ছা হলে)')->live(onBlur: true)
                ->helperText('গুগল ম্যাপ বা হোয়াটসঅ্যাপ লোকেশনের লিংক, বা "23.78, 90.42"।')
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                    if (filled($value) && ! MapLink::parse($value)) {
                        $fail('এখান থেকে লোকেশন পড়া গেল না।');
                    }
                }),
            Toggle::make('approve')->label('এখনই অনুমোদন দিন (ফোনে কথা হয়েছে)')
                ->visible(fn (Get $get) => filled($get('where')))
                ->helperText('লোকেশন ছাড়া অনুমোদন দেওয়া যায় না।'),
        ];
    }

    public static function createCook(array $data): Kitchen
    {
        return DB::transaction(function () use ($data) {
            $user = app(EnsurePerson::class)($data['phone'], $data['name'], Roles::COOK);
            if ($user->kitchen()->exists()) {
                throw ValidationException::withMessages(['phone' => 'এই নম্বরে আগে থেকেই একটা রান্নাঘর আছে।']);
            }

            $kitchen = new Kitchen([
                'user_id' => $user->id,
                'display_name' => trim($data['display_name']),
                'area_id' => $data['area_id'],
                'pickup_address' => filled($data['pickup_address'] ?? null) ? trim($data['pickup_address']) : null,
                'status' => KitchenStatus::Pending,
            ]);
            if ($point = MapLink::parse($data['where'] ?? null)) {
                $kitchen->location = $point;
            }
            $kitchen->save();

            if (! empty($data['approve']) && $kitchen->location) {
                $kitchen->approve(auth()->user());
            }

            return $kitchen;
        });
    }
}
