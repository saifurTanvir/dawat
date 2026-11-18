<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Links;
use App\Models\PinLink;
use App\Services\Kitchens\PinLinks;
use App\Support\AdminFeatures;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** F68: home pins that fell far from the cook's area — an admin decides. */
class PinReviews extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'পিন যাচাই বাকি';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && (PinLink::query()->needsReview()->exists());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PinLink::query()->needsReview()->with(['kitchen.user', 'kitchen.area']))
            ->defaultSort('submitted_at')
            ->paginated([10])
            ->columns([
                TextColumn::make('kitchen.display_name')->label('রান্নাঘর')->weight('semibold')
                    ->description(fn (PinLink $l) => $l->kitchen->user?->phone)
                    ->url(fn (PinLink $l) => Links::kitchen($l->kitchen_id)),
                TextColumn::make('kitchen.area.name')->label('এলাকা'),
                TextColumn::make('distance_m')->label('এলাকার কেন্দ্র থেকে')
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 1).' কিমি'),
                TextColumn::make('mode')->label('কীভাবে')
                    ->formatStateUsing(fn ($state) => $state === 'home' ? 'বাসা থেকে ফোনের GPS' : 'ম্যাপে নিজে'),
                TextColumn::make('submitted_at')->label('কবে')->since(),
            ])
            ->recordActions([
                Action::make('map')->label('ম্যাপে দেখুন')->icon(Heroicon::OutlinedMap)->color('gray')
                    ->url(fn (PinLink $l) => 'https://www.google.com/maps?q='.$l->submitted_location->lat.','.$l->submitted_location->lng, shouldOpenInNewTab: true),
                Action::make('accept')->label('ঠিক আছে, রাখুন')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->requiresConfirmation()->modalDescription('এই পিনটা রান্নাঘরের বাসার লোকেশন হবে।')
                    ->action(function (PinLink $l) {
                        app(PinLinks::class)->accept($l, auth()->user());
                        Notification::make()->title('লোকেশন রাখা হয়েছে')->success()->send();
                    }),
                Action::make('reject')->label('বাতিল')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->requiresConfirmation()->modalDescription('পিনটা রাখা হবে না। গৃহিণীকে নতুন করে পিন দিতে বলুন।')
                    ->action(function (PinLink $l) {
                        app(PinLinks::class)->reject($l, auth()->user());
                        Notification::make()->title('বাতিল করা হয়েছে')->send();
                    }),
            ]);
    }
}
