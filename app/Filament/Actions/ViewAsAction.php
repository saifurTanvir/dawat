<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Admin\Impersonate;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/** F51: the admin opens the app as this person, in a new tab. */
final class ViewAsAction
{
    /** @param  Closure(mixed): ?User  $userOf */
    public static function make(Closure $userOf, string $as = 'অ্যাপে তাঁর মতো করে দেখুন'): Action
    {
        return Action::make('viewAs')
            ->label($as)
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn ($record) => ($u = $userOf($record)) && Impersonate::canOpen(auth()->user(), $u))
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => ($userOf($record)?->name ?: $userOf($record)?->phone).'-এর অ্যাপ খুলবেন?')
            ->modalDescription('নতুন ট্যাবে তাঁর অ্যাপ তাঁর মতো করে খুলবে, ৩০ মিনিটের জন্য। কাজও করতে পারবেন — প্রতিটা কাজ খাতায় থাকবে, আর তিনি নোটিফিকেশন পাবেন যে দাওয়াত টিম তাঁর অ্যাপ খুলেছে। শেষে উপরের লাল পট্টিতে "ফিরে যান" চাপুন।')
            ->modalSubmitActionLabel('খুলুন')
            ->action(function ($record, Action $action) use ($userOf) {
                try {
                    $url = app(Impersonate::class)->start(auth()->user(), $userOf($record));
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title('প্রস্তুত')->body('নিচের বোতামে নতুন ট্যাবে অ্যাপ খুলবে। লিংক এক বারই কাজ করবে।')->success()->persistent()
                    ->actions([Action::make('open')->label('অ্যাপ খুলুন')->url($url, shouldOpenInNewTab: true)->button()])
                    ->send();
            });
    }
}
