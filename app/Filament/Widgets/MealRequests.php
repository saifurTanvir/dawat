<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Links;
use App\Models\MealChoice;
use App\Services\Orders\BookingPlanner;
use App\Services\Regular\TodayMenu;
use App\Support\AdminFeatures;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** F61: regular customers who will not eat today's dish and want something else. */
class MealRequests extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'আজ অন্য খাবার চান';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && (self::query()->exists());
    }

    private static function query(): Builder
    {
        return MealChoice::query()->where('choice', 'change')->whereDate('service_date', BookingPlanner::today());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => self::query()->with(['customer.user', 'customer.area', 'kitchen', 'shahidar', 'handler']))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw("status = 'handled'")->latest('id'))
            ->paginated([10])
            ->columns([
                TextColumn::make('customer.user.name')->label('গ্রাহক')->weight('semibold')
                    ->description(fn (MealChoice $c) => $c->customer->user->phone.' · '.($c->customer->area?->name ?? ''))
                    ->url(fn (MealChoice $c) => Links::person($c->customer->user)),
                TextColumn::make('meal_slot')->label('বেলা')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('menu_title')->label('রান্না ছিল')->description(fn (MealChoice $c) => $c->kitchen?->display_name)->placeholder('—')->wrap(),
                TextColumn::make('wish')->label('কী চান')->wrap(),
                TextColumn::make('shahidar.name')->label('শাহিদার')->placeholder('—'),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn (string $state, MealChoice $c) => $state === 'handled' ? 'ব্যবস্থা হয়েছে'.($c->handler ? ' ('.$c->handler->name.')' : '') : 'ব্যবস্থা বাকি')
                    ->color(fn (string $state) => $state === 'handled' ? 'success' : 'danger')
                    ->description(fn (MealChoice $c) => $c->handled_note),
            ])
            ->recordActions([
                Action::make('call')->label('কল')->icon(Heroicon::OutlinedPhone)->color('gray')
                    ->url(fn (MealChoice $c) => 'tel:+'.PhoneNumber::international($c->customer->user->phone)),
                Action::make('handled')->label('ব্যবস্থা হয়েছে')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (MealChoice $c) => $c->status !== 'handled')
                    ->schema([TextInput::make('note')->label('কী ব্যবস্থা হলো')->maxLength(300)->placeholder('যেমন: সালমা আপার কাছ থেকে মাছ-ভাত যাবে')])
                    ->action(function (MealChoice $c, array $data) {
                        app(TodayMenu::class)->handle($c, auth()->user(), $data['note'] ?? null);
                        Notification::make()->title('ব্যবস্থা হয়েছে বলে রাখা হলো')->success()->send();
                    }),
            ]);
    }
}
