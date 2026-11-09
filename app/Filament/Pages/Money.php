<?php

namespace App\Filament\Pages;

use App\Models\Kitchen;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Orders\Billing;
use App\Services\Orders\Settlements;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** F28: who holds cash, whom Dawat owes, who still has to pay. */
class Money extends Page
{
    protected string $view = 'filament.pages.money';

    protected static ?string $slug = 'money';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $navigationLabel = 'টাকার হিসাব';

    protected static ?string $title = 'টাকার হিসাব';

    protected static ?int $navigationSort = 50;

    protected function getViewData(): array
    {
        $money = app(Settlements::class);

        return [
            'shahidars' => $money->shahidarCash(),
            'cooks' => $money->cooksDue(),
            'owing' => $money->customersOwing(),
            'dues' => app(Billing::class)->dues()->filter(fn (array $r) => $r['customer']->paysByBill() || $r['billed_due'] > 0)->values(),
            'recent' => Settlement::with(['kitchen', 'shahidar'])->latest('id')->limit(15)->get(),
            'commission' => (float) config('dawat.orders.commission_percent', 0),
        ];
    }

    public function receiveCashAction(): Action
    {
        return Action::make('receiveCash')
            ->label('টাকা বুঝে পেয়েছি')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('এই শাহিদারের কাছে জমা সব নগদ টাকা দাওয়াত বুঝে পেল।')
            ->schema([TextInput::make('note')->label('নোট (ইচ্ছা হলে)')->maxLength(200)])
            ->action(function (array $arguments, array $data) {
                app(Settlements::class)->receiveFromShahidar(User::findOrFail($arguments['shahidar']), auth()->user(), $data['note'] ?? null);
                Notification::make()->title('জমা নেওয়া হয়েছে')->success()->send();
            });
    }

    public function payCookAction(): Action
    {
        return Action::make('payCook')
            ->label('পরিশোধ করেছি')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('এই গৃহিণীর এখন পর্যন্ত সব পাওনা পরিশোধ হিসেবে লেখা হবে।')
            ->schema([TextInput::make('note')->label('কীভাবে দিলেন (নগদ/বিকাশ, নোট)')->maxLength(200)])
            ->action(function (array $arguments, array $data) {
                app(Settlements::class)->payCook(Kitchen::findOrFail($arguments['kitchen']), auth()->user(), $data['note'] ?? null);
                Notification::make()->title('পরিশোধ লেখা হয়েছে')->success()->send();
            });
    }
}
