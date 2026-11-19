<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\CallRequestActions;
use App\Models\CallRequest;
use App\Services\Admin\CallOnboarding;
use App\Support\AdminFeatures;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** F70: calls that are finished but whose person is not in the system yet. */
class CallOnboardingPending extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'কথা হয়েছে, সিস্টেমে যোগ বাকি';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && CallOnboarding::pending()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CallOnboarding::pending()->with('user'))
            ->defaultSort('done_at', 'desc')
            ->paginated([10])
            ->columns([
                TextColumn::make('name')->label('কে')->weight('semibold')
                    ->state(fn (CallRequest $r) => $r->name ?: ($r->user?->name ?: $r->phone))
                    ->description(fn (CallRequest $r) => $r->phone),
                CallRequestActions::reasonColumn(),
                TextColumn::make('guess')->label('আন্দাজ')->badge()->color('gray')
                    ->state(fn (CallRequest $r) => CallOnboarding::ROLES[CallOnboarding::guessRole($r)]),
                CallRequestActions::talksColumn(),
            ])
            ->recordActions([CallRequestActions::onboard()]);
    }
}
