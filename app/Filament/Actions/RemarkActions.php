<?php

namespace App\Filament\Actions;

use App\Models\Remark;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\DhakaTime;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

/**
 * F63: admins' remarks on anyone — cooks, customers, shahidars — with an
 * optional follow-up day that shows on the dashboard until marked done.
 */
final class RemarkActions
{
    /** @param  Closure(mixed): ?User  $userOf  the person behind the row or page */
    public static function add(Closure $userOf, string $name = 'remark'): Action
    {
        return Action::make($name)
            ->label('রিমার্কস')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->color('gray')
            ->visible(fn ($record) => $userOf($record) !== null)
            ->modalHeading(fn ($record) => 'রিমার্কস — '.self::who($userOf($record)))
            ->modalDescription('শুধু অ্যাডমিনরা দেখবেন।')
            ->modalSubmitActionLabel('রাখুন')
            ->schema(fn ($record) => [
                Textarea::make('body')->label('নতুন রিমার্কস')->required()->maxLength(1000)->rows(3)
                    ->placeholder('যেমন: OTP দিয়ে যাচাই করেননি, কাল সকালে আবার ফোন দিতে হবে'),
                DatePicker::make('follow_up_on')->label('ফলো-আপের দিন (ইচ্ছা হলে)')->native(false)
                    ->minDate(now(DhakaTime::TZ)->startOfDay())->displayFormat('j M Y')
                    ->helperText('সেদিন ড্যাশবোর্ডের "আজকের ফলো-আপ"-এ আসবে।'),
                ViewField::make('history')->hiddenLabel()->view('filament.remarks.history')
                    ->viewData(['remarks' => $userOf($record)?->remarks()->with(['author', 'doneBy'])->limit(20)->get() ?? collect()]),
            ])
            ->action(function ($record, array $data) use ($userOf) {
                $user = $userOf($record);
                Remark::create([
                    'user_id' => $user->id,
                    'body' => trim($data['body']),
                    'follow_up_on' => $data['follow_up_on'] ?? null,
                    'created_by' => auth()->id(),
                ]);
                Notification::make()->title('রিমার্কস রাখা হয়েছে')->success()->send();
            });
    }

    /** A person's name, else their kitchen's, else the phone number. */
    public static function who(?User $user): string
    {
        return $user ? ($user->name ?: ($user->kitchen?->display_name ?: $user->phone)) : '';
    }

    /** The latest remark in a list, with its follow-up day. $path is "latestRemark" or "user.latestRemark". */
    public static function column(string $path = 'latestRemark'): TextColumn
    {
        return TextColumn::make($path.'.body')
            ->label('রিমার্কস')
            ->placeholder('—')
            ->limit(40)
            ->tooltip(fn ($state) => Str::length((string) $state) > 40 ? $state : null)
            ->wrap()
            ->description(function ($record) use ($path) {
                $remark = data_get($record, $path);
                if (! $remark?->follow_up_on) {
                    return null;
                }
                if ($remark->done_at) {
                    return 'ফলো-আপ হয়ে গেছে';
                }

                return 'ফলো-আপ: '.$remark->follow_up_on->format('j M').($remark->follow_up_on->toDateString() <= BookingPlanner::today() ? ' (আজ/বাকি)' : '');
            })
            ->toggleable();
    }
}
