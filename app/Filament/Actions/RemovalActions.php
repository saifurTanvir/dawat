<?php

namespace App\Filament\Actions;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Admin\Removal;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * F42: delete, unpair and undo buttons for the admin panel. Every delete
 * shows what will go, refuses when settled money is involved, and asks the
 * admin to type "মুছুন".
 */
final class RemovalActions
{
    public const WORD = 'মুছুন';

    private static function removal(): Removal
    {
        return app(Removal::class);
    }

    /** Typed confirmation, so a delete is never one stray click. */
    private static function confirmField(): TextInput
    {
        return TextInput::make('confirm')
            ->label('নিশ্চিত করতে লিখুন: '.self::WORD)
            ->required()
            ->in([self::WORD])
            ->validationMessages(['in' => 'ঠিক "'.self::WORD.'" লিখুন।']);
    }

    /** @param  list<string>  $blockers */
    private static function describe(string $summary, array $blockers, ?string $instead = null): string
    {
        return $blockers
            ? '⛔ মোছা যাবে না: '.implode(' ', $blockers).($instead ? ' এর বদলে '.$instead : '')
            : $summary.' এটা আর ফেরানো যাবে না।';
    }

    /** Runs a removal; a refusal becomes a red notice instead of an error page. */
    private static function attempt(Action $action, Closure $do, string $done, ?string $redirect, $livewire): void
    {
        try {
            $do();
        } catch (ValidationException $e) {
            Notification::make()->title('মোছা হয়নি')->body(collect($e->errors())->flatten()->implode(' '))->danger()->persistent()->send();
            $action->halt();

            return;
        }
        Notification::make()->title($done)->success()->send();
        if ($redirect && ($livewire instanceof ViewRecord || $livewire instanceof Page)) {
            $livewire->redirect($redirect);
        }
    }

    /* ---------- customers ---------- */

    public static function unpair(): Action
    {
        return Action::make('unpair')
            ->label('জুটি খুলে দিন')
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('warning')
            ->visible(fn (Customer $record) => $record->pairing !== null)
            ->modalDescription('গৃহিণী আর শাহিদারের সাথে জুটি শেষ হবে, গ্রাহক "অপেক্ষায়" যাবেন। আজ থেকে বুক করা বক্স বাতিল হবে। পরে আবার জুটি বাঁধা যাবে।')
            ->schema([Textarea::make('reason')->label('কারণ')->required()->maxLength(300)->rows(2)->default('ভুল করে জুটি বাঁধা হয়েছিল')])
            ->action(function (Customer $record, array $data) {
                $n = self::removal()->unpair($record, auth()->user(), $data['reason']);
                Notification::make()->title('জুটি খোলা হয়েছে')
                    ->body($n ? Order::banglaDigits((string) $n).'টা আগামী বক্স বাতিল হয়েছে।' : null)->success()->send();
            });
    }

    public static function restart(): Action
    {
        return Action::make('restart')
            ->label('আবার চালু করুন')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->visible(fn (Customer $record) => $record->status === CustomerStatus::Stopped)
            ->requiresConfirmation()
            ->modalDescription('গ্রাহক আবার "অপেক্ষায়" যাবেন; তারপর জুটি বাঁধুন।')
            ->action(function (Customer $record) {
                self::removal()->restart($record);
                Notification::make()->title('আবার চালু হয়েছে — এখন জুটি বাঁধুন')->success()->send();
            });
    }

    public static function deleteCustomer(): Action
    {
        return Action::make('deleteCustomer')
            ->label('গ্রাহক মুছুন')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading(fn (Customer $record) => ($record->user?->name ?: 'গ্রাহক').' মুছবেন?')
            ->modalDescription(fn (Customer $record) => self::describe(self::removal()->customerSummary($record), self::removal()->customerBlockers($record), '"নিয়মিত খাবার বন্ধ" বা "জুটি খুলে দিন" ব্যবহার করুন।'))
            ->modalSubmitActionLabel('মুছে ফেলুন')
            ->modalSubmitAction(fn (Customer $record) => self::removal()->customerBlockers($record) ? false : null)
            ->schema(fn (Customer $record) => self::removal()->customerBlockers($record) ? [] : [
                Checkbox::make('with_account')->label('ফোন নম্বরের অ্যাকাউন্টও মুছুন (টেস্টের জন্য বানানো হলে)')
                    ->helperText('ব্যক্তি গৃহিণী/শাহিদার হলে অ্যাকাউন্ট থেকে যাবে।'),
                self::confirmField(),
            ])
            ->action(fn (Customer $record, array $data, Action $action, $livewire) => self::attempt(
                $action,
                fn () => self::removal()->deleteCustomer($record, (bool) ($data['with_account'] ?? false)),
                'গ্রাহক মুছে ফেলা হয়েছে',
                CustomerResource::getUrl('index'),
                $livewire,
            ));
    }

    /* ---------- kitchens ---------- */

    public static function deleteKitchen(): Action
    {
        return Action::make('deleteKitchen')
            ->label('রান্নাঘর মুছুন')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading(fn (Kitchen $record) => $record->display_name.' মুছবেন?')
            ->modalDescription(fn (Kitchen $record) => self::describe(self::removal()->kitchenSummary($record), self::removal()->kitchenBlockers($record), 'রান্নাঘর "সাময়িক বন্ধ" করুন।'))
            ->modalSubmitActionLabel('মুছে ফেলুন')
            ->modalSubmitAction(fn (Kitchen $record) => self::removal()->kitchenBlockers($record) ? false : null)
            ->schema(fn (Kitchen $record) => self::removal()->kitchenBlockers($record) ? [] : [
                Checkbox::make('with_account')->label('ফোন নম্বরের অ্যাকাউন্টও মুছুন (টেস্টের জন্য বানানো হলে)'),
                self::confirmField(),
            ])
            ->action(fn (Kitchen $record, array $data, Action $action, $livewire) => self::attempt(
                $action,
                fn () => self::removal()->deleteKitchen($record, auth()->user(), (bool) ($data['with_account'] ?? false)),
                'রান্নাঘর মুছে ফেলা হয়েছে',
                KitchenResource::getUrl('index'),
                $livewire,
            ));
    }

    /* ---------- shahidars ---------- */

    public static function removeShahidar(): Action
    {
        return Action::make('removeRole')
            ->label('শাহিদার থেকে বাদ')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->modalHeading(fn (User $record) => ($record->name ?: $record->phone).' — শাহিদার থেকে বাদ দেবেন?')
            ->modalDescription(fn (User $record) => ($b = self::removal()->shahidarBlockers($record))
                ? '⛔ '.implode(' ', $b)
                : self::removal()->shahidarSummary($record).' পুরোনো ডেলিভারির হিসাবে নাম থেকে যাবে।')
            ->modalSubmitActionLabel('বাদ দিন')
            ->modalSubmitAction(fn (User $record) => self::removal()->shahidarBlockers($record) ? false : null)
            ->requiresConfirmation()
            ->action(fn (User $record, Action $action, $livewire) => self::attempt(
                $action,
                fn () => self::removal()->removeShahidar($record, auth()->user()),
                'শাহিদার থেকে বাদ দেওয়া হয়েছে',
                null,
                $livewire,
            ));
    }

    /* ---------- orders, posts ---------- */

    public static function deleteOrder(): Action
    {
        return Action::make('deleteOrder')
            ->label('বক্স মুছুন')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => 'বক্স '.$record->code().' মুছবেন?')
            ->modalDescription(fn (Order $record) => self::describe('বক্সটা তালিকা আর হিসাব থেকে চলে যাবে (টেস্ট বা ভুল বক্সের জন্য)।', self::removal()->orderBlockers($record), 'দরকার হলে "আজ বাদ" দিন।'))
            ->modalSubmitActionLabel('মুছে ফেলুন')
            ->modalSubmitAction(fn (Order $record) => self::removal()->orderBlockers($record) ? false : null)
            ->action(fn (Order $record, Action $action, $livewire) => self::attempt(
                $action,
                fn () => self::removal()->deleteOrder($record),
                'বক্স মুছে ফেলা হয়েছে',
                null,
                $livewire,
            ));
    }

    public static function deletePost(?string $redirect = null): Action
    {
        return Action::make('deletePost')
            ->label('পোস্ট মুছুন')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('পোস্ট, তার ছবি-মন্তব্য আর কে দেখেছে সব মুছে যাবে। এর অর্ডারগুলো থাকবে। শুধু লুকাতে চাইলে "লুকান" ব্যবহার করুন।')
            ->modalSubmitActionLabel('মুছে ফেলুন')
            ->action(function (Post $record, $livewire) use ($redirect) {
                $record->delete();
                Notification::make()->title('পোস্ট মুছে ফেলা হয়েছে')->success()->send();
                if ($redirect && $livewire instanceof ViewRecord) {
                    $livewire->redirect($redirect);
                }
            });
    }

    /* ---------- people ---------- */

    public static function deleteUser(): Action
    {
        return Action::make('deleteUser')
            ->label('অ্যাকাউন্ট মুছুন')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (User $record) => $record->id !== auth()->id())
            ->modalHeading(fn (User $record) => ($record->name ?: $record->phone).' — অ্যাকাউন্ট মুছবেন?')
            ->modalDescription(fn (User $record) => self::describe(
                'অ্যাকাউন্ট, এর রান্নাঘর/নিয়মিত বুকিং, অর্ডার আর শাহিদারের কাজ সব মুছে যাবে।',
                self::removal()->accountBlockers($record),
                'অ্যাকাউন্ট ব্লক করুন।',
            ))
            ->modalSubmitActionLabel('মুছে ফেলুন')
            ->modalSubmitAction(fn (User $record) => self::removal()->accountBlockers($record) ? false : null)
            ->schema(fn (User $record) => self::removal()->accountBlockers($record) ? [] : [self::confirmField()])
            ->action(fn (User $record, Action $action, $livewire) => self::attempt(
                $action,
                fn () => self::removal()->deleteAccount($record, auth()->user()),
                'অ্যাকাউন্ট মুছে ফেলা হয়েছে',
                UserResource::getUrl('index'),
                $livewire,
            ));
    }
}
