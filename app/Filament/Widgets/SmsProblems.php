<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\LoginHelpActions;
use App\Filament\Support\Links;
use App\Models\SmsLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** F50: codes that did not go, or did not arrive, in the last two days. */
class SmsProblems extends TableWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'কোড যায়নি / আসেনি (৪৮ ঘণ্টা)';

    public static function canView(): bool
    {
        return self::problems()->exists();
    }

    private static function problems(): Builder
    {
        // The latest problem per phone, unless that person signed in afterwards.
        return SmsLog::query()
            ->whereIn('status', ['failed', 'reported'])
            ->where('created_at', '>=', now()->subHours(48))
            ->whereIn('id', SmsLog::selectRaw('MAX(id)')->whereIn('status', ['failed', 'reported'])->groupBy('phone'))
            ->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.phone', 'sms_logs.phone')
                ->whereColumn('users.updated_at', '>', 'sms_logs.created_at')->whereNotNull('users.phone_verified_at'));
    }

    public function table(Table $table): Table
    {
        // The account is created only when a code or link is actually made.
        $userOf = fn (SmsLog $log) => $log->user ?? new User(['phone' => $log->phone]);

        return $table
            ->query(fn () => self::problems()->with('user'))
            ->defaultSort('id', 'desc')
            ->paginated([5])
            ->columns([
                TextColumn::make('created_at')->label('কবে')->since(),
                TextColumn::make('phone')->label('নম্বর')->copyable()
                    ->description(fn (SmsLog $l) => $l->user?->name)
                    ->url(fn (SmsLog $l) => Links::person($l->user)),
                TextColumn::make('status')->label('কী হয়েছে')->badge()->color('danger')
                    ->formatStateUsing(fn (string $state) => SmsLog::STATUS_LABELS[$state] ?? $state),
                TextColumn::make('error')->label('কারণ')->placeholder('—')->wrap(),
            ])
            ->recordActions([
                LoginHelpActions::whatsappCode($userOf),
                LoginHelpActions::loginLink($userOf),
            ]);
    }
}
