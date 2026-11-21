<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Links;
use App\Models\GuestMeal;
use App\Models\Order;
use App\Models\Post;
use App\Services\Regular\GuestMeals;
use App\Support\AdminFeatures;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;

/** F64: guest meals asked for today and tomorrow, with what the cook said. */
class GuestMealRequests extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'অতিথির খাবার';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && (GuestMeals::current()->exists());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => GuestMeals::current())
            ->paginated([10])
            ->columns([
                TextColumn::make('customer.user.name')->label('গ্রাহক')->weight('semibold')
                    ->formatStateUsing(fn ($state, GuestMeal $g) => $state ?: $g->customer->user->phone)
                    ->description(fn (GuestMeal $g) => $g->customer->user->phone.' · '.($g->customer->area?->name ?? ''))
                    ->url(fn (GuestMeal $g) => Links::person($g->customer->user)),
                TextColumn::make('service_date')->label('কবে')->formatStateUsing(fn ($state, GuestMeal $g) => $g->when())
                    ->description(fn (GuestMeal $g) => Order::banglaDigits($g->plates).' প্লেট'),
                TextColumn::make('note')->label('নোট')->placeholder('—')->wrap(),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী')->placeholder('—')
                    ->description(fn (GuestMeal $g) => $g->kitchen?->user?->phone),
                TextColumn::make('shahidar.name')->label('এলাকা ম্যানেজার')->placeholder('—'),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn (string $state, GuestMeal $g) => ($g->followup_at && $state === 'asked' ? '৫ মিনিট পার — ' : '').GuestMeal::STATUS[$state])
                    ->color(fn (string $state, GuestMeal $g) => match ($state) {
                        'arranged' => 'success',
                        'cancelled' => 'gray',
                        'cook_no' => 'danger',
                        'cook_yes' => 'info',
                        default => $g->followup_at ? 'danger' : 'warning',
                    })
                    ->description(fn (GuestMeal $g) => $g->order ? 'বক্স '.$g->order->code().' · '.$g->order->kitchen?->display_name : $g->handled_note),
            ])
            ->recordActions([
                Action::make('callCook')->label('গৃহিণীকে কল')->icon(Heroicon::OutlinedPhone)->color('gray')
                    ->visible(fn (GuestMeal $g) => $g->isOpen() && $g->kitchen?->user)
                    ->url(fn (GuestMeal $g) => 'tel:+'.PhoneNumber::international($g->kitchen->user->phone)),
                Action::make('cookYes')->label('গৃহিণী পারবেন')->icon(Heroicon::OutlinedCheck)->color('info')
                    ->visible(fn (GuestMeal $g) => $g->status === 'asked')
                    ->requiresConfirmation()->modalDescription('ফোনে গৃহিণী রাজি হয়েছেন? তাঁর পোস্ট থেকে বুক হবে।')
                    ->action(fn (GuestMeal $g) => $this->done(fn () => app(GuestMeals::class)->cookReply($g, true, auth()->user()), 'রাখা হলো')),
                Action::make('fromPost')->label('পোস্ট থেকে')->icon(Heroicon::OutlinedShoppingBag)->color('success')
                    ->visible(fn (GuestMeal $g) => $g->isOpen() && $g->service_date->isSameDay(now('Asia/Dhaka')))
                    ->schema(fn (GuestMeal $g) => [
                        Select::make('post_id')->label('কোন পোস্ট থেকে')->required()
                            ->options(app(GuestMeals::class)->candidatePosts($g)->mapWithKeys(fn (Post $p) => [
                                $p->id => $p->kitchen->display_name.' — '.$p->title.' · ৳'.Order::banglaDigits(intdiv($p->price_paisa, 100)).' · বাকি '.Order::banglaDigits($p->plates_left),
                            ]))
                            ->helperText('আজকের লাইভ পোস্ট, যেখানে এত প্লেট বাকি আছে।'),
                    ])
                    ->action(fn (GuestMeal $g, array $data) => $this->done(fn () => app(GuestMeals::class)->arrangeFromPost($g, Post::findOrFail($data['post_id']), auth()->user()), 'বুক হয়েছে, গ্রাহককে জানানো হলো')),
                Action::make('other')->label('অন্যভাবে')->icon(Heroicon::OutlinedHandThumbUp)->color('gray')
                    ->visible(fn (GuestMeal $g) => $g->isOpen())
                    ->schema([TextInput::make('note')->label('কী ব্যবস্থা হলো')->maxLength(300)->placeholder('যেমন: রাহেলা ২ প্লেট বিরিয়ানি দেবেন, ৳৩০০')])
                    ->action(fn (GuestMeal $g, array $data) => $this->done(fn () => app(GuestMeals::class)->close($g, auth()->user(), $data['note'] ?? null), 'গ্রাহককে জানানো হলো')),
                Action::make('decline')->label('হচ্ছে না')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (GuestMeal $g) => $g->isOpen())
                    ->schema([TextInput::make('note')->label('গ্রাহককে কী বলবেন')->maxLength(300)])
                    ->action(fn (GuestMeal $g, array $data) => $this->done(fn () => app(GuestMeals::class)->close($g, auth()->user(), $data['note'] ?? null, true), 'গ্রাহককে জানানো হলো')),
            ]);
    }

    private function done(callable $work, string $title): void
    {
        try {
            $work();
            Notification::make()->title($title)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
