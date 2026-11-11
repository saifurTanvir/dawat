<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Otp\LoginHelp;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpThrottledException;
use App\Support\Seo;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * F50: "লগইনে সাহায্য" — when someone's SMS code does not arrive: send it
 * again, send a code on WhatsApp, or give a one-time login link / QR.
 */
final class LoginHelpActions
{
    /** @param  Closure(mixed): ?User  $userOf  the person behind the page's record */
    public static function group(Closure $userOf): ActionGroup
    {
        return ActionGroup::make([
            self::resend($userOf),
            self::whatsappCode($userOf),
            self::loginLink($userOf),
        ])->label('লগইনে সাহায্য')->icon(Heroicon::OutlinedKey)->button()->color('gray');
    }

    /** Never for admin accounts (they sign in to the panel with a password). */
    private static function allowed(Closure $userOf): Closure
    {
        return fn ($record) => ($u = $userOf($record)) !== null && ! ($u->exists && $u->hasRole('admin'));
    }

    public static function resend(Closure $userOf): Action
    {
        return Action::make('resendSms')
            ->visible(self::allowed($userOf))
            ->label('আবার SMS পাঠান')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->requiresConfirmation()
            ->modalDescription(fn ($record) => 'নতুন লগইন কোড SMS-এ যাবে: '.$userOf($record)?->phone)
            ->action(function ($record) use ($userOf) {
                try {
                    app(OtpService::class)->request($userOf($record)->phone);
                    Notification::make()->title('SMS পাঠানো হয়েছে')->body('না এলে হোয়াটসঅ্যাপে কোড বা লগইন লিংক দিন।')->success()->send();
                } catch (OtpThrottledException $e) {
                    Notification::make()->title('একটু পরে আবার চেষ্টা করুন')->body('এই নম্বরে এইমাত্র কোড গেছে ('.$e->retryAfterSeconds.' সেকেন্ড)।')->warning()->send();
                } catch (OtpDeliveryFailedException $e) {
                    Notification::make()->title('SMS যায়নি')->body($e->getMessage().' — হোয়াটসঅ্যাপে কোড বা লগইন লিংক দিন।')->danger()->persistent()->send();
                }
            });
    }

    public static function whatsappCode(Closure $userOf): Action
    {
        return Action::make('whatsappCode')
            ->visible(self::allowed($userOf))
            ->label('হোয়াটসঅ্যাপে কোড পাঠান')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->requiresConfirmation()
            ->modalDescription('নতুন কোড তৈরি হবে (৩০ মিনিট চালু)। আপনার হোয়াটসঅ্যাপ থেকে পাঠাবেন — ফ্রি। এভাবে ঢুকলে অ্যাকাউন্টে লেখা থাকবে "SMS-এ যাচাই হয়নি"।')
            ->action(function ($record, Action $action) use ($userOf) {
                $user = $userOf($record);
                try {
                    $code = app(LoginHelp::class)->whatsappCode($user, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->title('হয়নি')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                    $action->halt();

                    return;
                }
                $text = "দাওয়াতে ঢোকার কোড: {$code}\ndawat.cloud খুলে নম্বর {$user->phone} দিন, তারপর এই কোড। ৩০ মিনিট চালু থাকবে। — দাওয়াত টিম (".Seo::phoneBangla().')';
                Notification::make()->title('কোড: '.$code)->body('নিচের বোতামে হোয়াটসঅ্যাপ খুলবে, মেসেজ লেখা আছে — শুধু পাঠান।')->persistent()->success()
                    ->actions([Action::make('wa')->label('হোয়াটসঅ্যাপে খুলুন')->url(LoginHelp::whatsappUrl($user->phone, $text), shouldOpenInNewTab: true)->button()])
                    ->send();
            });
    }

    public static function loginLink(Closure $userOf): Action
    {
        return Action::make('loginLink')
            ->visible(self::allowed($userOf))
            ->label('লগইন লিংক / QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->requiresConfirmation()
            ->modalDescription('এক বার ব্যবহারের লিংক, ৩০ মিনিট চালু। সামনে থাকলে QR স্ক্যান করাবেন, দূরে থাকলে হোয়াটসঅ্যাপে পাঠাবেন। আগে ফোন করে নিশ্চিত হোন নম্বরটা ঠিক মানুষের।')
            ->action(function ($record, Action $action) use ($userOf) {
                $user = $userOf($record);
                try {
                    $url = app(LoginHelp::class)->link($user, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->title('হয়নি')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
                    $action->halt();

                    return;
                }
                $text = "দাওয়াতে ঢুকতে এই লিংকে চাপুন (এক বারই কাজ করবে, ৩০ মিনিট):\n{$url}\n— দাওয়াত টিম";
                Notification::make()->title('লগইন লিংক তৈরি হয়েছে')->body($url)->persistent()->success()
                    ->actions([
                        Action::make('wa')->label('হোয়াটসঅ্যাপে পাঠান')->url(LoginHelp::whatsappUrl($user->phone, $text), shouldOpenInNewTab: true)->button(),
                        Action::make('qr')->label('QR দেখান')->url(route('admin.login-qr', ['u' => $url]), shouldOpenInNewTab: true)->button()->color('gray'),
                    ])
                    ->send();
            });
    }
}
