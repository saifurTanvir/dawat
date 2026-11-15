<?php

namespace App\Filament\Actions;

use App\Enums\CallRequestStatus;
use App\Models\CallRequest;
use App\Support\DhakaTime;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** F66: the buttons on every row of the call list — mobile call, WhatsApp, notes, done. */
final class CallRequestActions
{
    /** @return list<Action> */
    public static function row(): array
    {
        return [
            Action::make('call')->label('মোবাইলে কল')->icon(Heroicon::OutlinedPhone)->color('success')
                ->url(fn (CallRequest $r) => $r->telUrl()),
            Action::make('whatsapp')->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)->color('success')
                ->url(fn (CallRequest $r) => $r->whatsappUrl(), shouldOpenInNewTab: true),
            self::note(),
        ];
    }

    /** Write what was said and where it stands now. */
    public static function note(): Action
    {
        return Action::make('note')->label('রিমার্কস')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
            ->modalHeading(fn (CallRequest $r) => 'রিমার্কস — '.($r->name ?: $r->phone))
            ->modalDescription(fn (CallRequest $r) => 'কেন: '.$r->reason)
            ->modalSubmitActionLabel('রাখুন')
            ->fillForm(fn (CallRequest $r) => ['status' => $r->status->value])
            ->schema(fn (CallRequest $r) => [
                Textarea::make('body')->label('কী কথা হলো')->rows(3)->maxLength(1000)
                    ->placeholder('যেমন: বক্স কাল সকালে দেব বলেছি, শুক্রবার থেকে খাবার দেবেন'),
                Radio::make('status')->label('অবস্থা')->options(CallRequestStatus::options())->inline()->required(),
                Placeholder::make('history')->label('আগের রিমার্কস')
                    ->content(fn () => self::history($r))
                    ->visible(fn () => ! empty($r->notes)),
            ])
            ->action(function (CallRequest $r, array $data) {
                if (filled($data['body'] ?? null)) {
                    $r->addNote($data['body'], auth()->user());
                }
                self::setStatus($r, CallRequestStatus::from($data['status']));
                $r->save();
                Notification::make()->title('রাখা হয়েছে')->success()->send();
            });
    }

    public static function done(): Action
    {
        return Action::make('done')->label('কথা হয়েছে')->icon(Heroicon::OutlinedCheck)->color('success')
            ->visible(fn (CallRequest $r) => $r->status !== CallRequestStatus::Done)
            ->action(function (CallRequest $r) {
                self::setStatus($r, CallRequestStatus::Done);
                $r->save();
                Notification::make()->title('কথা হয়েছে')->success()->send();
            });
    }

    public static function setStatus(CallRequest $r, CallRequestStatus $status): void
    {
        $r->status = $status;
        if ($status === CallRequestStatus::Done) {
            $r->done_at ??= now();
            $r->done_by ??= auth()->id();
        } else {
            $r->done_at = null;
            $r->done_by = null;
        }
    }

    public static function history(CallRequest $r): HtmlString
    {
        $rows = collect($r->notes ?? [])->map(fn (array $n) => '<div style="margin-bottom:.4rem"><div>'.e($n['body']).'</div><div style="font-size:.75rem;opacity:.7">'
            .e(Carbon::parse($n['at'])->timezone(DhakaTime::TZ)->format('j M, g:i A')).($n['by'] ? ' · '.e($n['by']) : '').'</div></div>');

        return new HtmlString($rows->implode(''));
    }
}
