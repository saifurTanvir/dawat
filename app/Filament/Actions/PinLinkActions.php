<?php

namespace App\Filament\Actions;

use App\Models\Kitchen;
use App\Models\PinLink;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/** F68: make a cook's "drop your home pin" link and send it on WhatsApp. */
final class PinLinkActions
{
    public static function make(): Action
    {
        return Action::make('pinLink')
            ->label(fn (Kitchen $record) => $record->location ? 'লোকেশন লিংক (আবার)' : 'লোকেশন লিংক')
            ->icon(Heroicon::OutlinedMapPin)
            ->color(fn (Kitchen $record) => $record->location ? 'gray' : 'warning')
            ->modalHeading('গৃহিণীর লোকেশন লিংক')
            ->modalDescription('লগইন ছাড়াই খোলে, শুধু এই রান্নাঘরের বাসার পিন দেওয়া যায়, ৭ দিন চলে। পিন এলাকা থেকে অনেক দূরে পড়লে ড্যাশবোর্ডে "পিন যাচাই বাকি"-তে আসবে।')
            ->fillForm(fn (Kitchen $record) => ['message' => PinLink::forKitchen($record, auth()->user())->message()])
            ->schema(fn (Kitchen $record) => [
                Textarea::make('message')->label('মেসেজ (কপি করে পাঠান)')->rows(8)->readOnly(),
                Placeholder::make('wa')->label('WhatsApp')->hiddenLabel()->content(function () use ($record) {
                    $url = 'https://wa.me/'.PhoneNumber::international($record->user->phone).'?text='.rawurlencode(PinLink::forKitchen($record)->message());

                    return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener" style="display:inline-block;background:#16a34a;color:#fff;font-weight:600;padding:.5rem 1rem;border-radius:.5rem">WhatsApp-এ পাঠান</a>');
                }),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('বন্ধ করুন');
    }

    /** F76: a link went to the wrong person — cancel the old one and make a new one. */
    public static function renew(): Action
    {
        return Action::make('renewPinLink')
            ->label('নতুন লোকেশন লিংক (পুরনোটা বাতিল)')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('পুরনো লোকেশন লিংক বাতিল করবেন?')
            ->modalDescription('এই রান্নাঘরের আগের সব লোকেশন লিংক আর কাজ করবে না। নতুন লিংক বানানো হবে — সেটা গৃহিণীকে আবার পাঠাতে হবে।')
            ->modalSubmitActionLabel('বাতিল করে নতুন বানান')
            ->action(function (Kitchen $record): void {
                $link = PinLink::renewForKitchen($record, auth()->user());
                Notification::make()->success()->title('নতুন লিংক তৈরি হয়েছে')->body($link->url())->persistent()->send();
            });
    }
}
