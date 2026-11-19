<?php

namespace App\Filament\Actions;

use App\Enums\CallRequestStatus;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\Order;
use App\Services\Admin\CallOnboarding;
use App\Support\DhakaTime;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** F66: the buttons on every row of the call list — mobile call, WhatsApp, remarks (which also closes it). */
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
            ->fillForm(fn (CallRequest $r) => ['status' => $r->status->value, ...self::onboardDefaults($r)])
            ->schema(fn (CallRequest $r) => [
                Textarea::make('body')->label('কী কথা হলো')->rows(3)->maxLength(1000)
                    ->placeholder('যেমন: বক্স কাল সকালে দেব বলেছি, শুক্রবার থেকে খাবার দেবেন'),
                // F69: one tick closes it — it leaves "যোগাযোগ করতে হবে".
                Checkbox::make('close')->label('কথা শেষ — তালিকা থেকে সরিয়ে দিন')->live()
                    ->helperText('উপরের লেখাটাই থাকবে বন্ধ করার কারণ হিসেবে।'),
                Radio::make('status')->label('অবস্থা')->options(CallRequestStatus::options())->inline()->required()
                    ->hidden(fn ($get) => (bool) $get('close')),
                // F70: closing puts the person in the system if they aren't yet.
                ...self::onboardFields($r, fn ($get) => (bool) $get('close')),
                Placeholder::make('history')->label('আগের রিমার্কস')
                    ->content(fn () => self::history($r))
                    ->visible(fn () => ! empty($r->notes)),
            ])
            ->action(function (CallRequest $r, array $data) {
                if (filled($data['body'] ?? null)) {
                    $r->addNote($data['body'], auth()->user());
                }
                $close = (bool) ($data['close'] ?? false);
                self::setStatus($r, $close ? CallRequestStatus::Done : CallRequestStatus::from($data['status']));
                $r->save();
                $added = $close ? self::onboardFrom($r, $data) : null;
                Notification::make()->title($close ? 'রাখা হয়েছে, তালিকা থেকে সরানো হলো' : 'রাখা হয়েছে')
                    ->body($added)->success()->send();
            });
    }

    /** F70: a finished call whose person is not in the system yet — add them now. */
    public static function onboard(): Action
    {
        return Action::make('onboard')->label('সিস্টেমে যোগ করুন')->icon(Heroicon::OutlinedUserPlus)->color('primary')
            ->modalHeading(fn (CallRequest $r) => 'সিস্টেমে যোগ — '.($r->name ?: $r->phone))
            ->modalDescription(fn (CallRequest $r) => 'কেন: '.$r->reason)
            ->modalSubmitActionLabel('যোগ করুন')
            ->fillForm(fn (CallRequest $r) => self::onboardDefaults($r))
            ->schema(fn (CallRequest $r) => [
                ...self::onboardFields($r, fn () => true),
                Placeholder::make('history')->label('রিমার্কস')->content(fn () => self::history($r))->visible(fn () => ! empty($r->notes)),
            ])
            ->action(function (CallRequest $r, array $data) {
                Notification::make()->title(self::onboardFrom($r, $data) ?? 'রাখা হয়েছে')->success()->send();
            });
    }

    /** @return array<string, mixed> */
    public static function onboardDefaults(CallRequest $r): array
    {
        $area = CallOnboarding::guessArea($r);

        return [
            'onboard_role' => CallOnboarding::guessRole($r),
            'onboard_name' => $r->name ?: $r->user?->name,
            'area_id' => $area,
            'area_ids' => $area ? [$area] : [],
        ];
    }

    /**
     * What to add the person as. Shown only when they are not in the system;
     * otherwise a line saying how they already are.
     *
     * @param  Closure  $when  (fn ($get) => bool) — e.g. only when "কথা শেষ" is ticked
     */
    public static function onboardFields(CallRequest $r, Closure $when): array
    {
        $existing = CallOnboarding::existing($r);
        if ($existing) {
            return [Placeholder::make('in_system')->label('সিস্টেমে')->content('আগে থেকেই আছেন: '.$existing)->visible($when)];
        }
        $role = fn ($get) => $get('onboard_role');

        return [
            Radio::make('onboard_role')->label('সিস্টেমে নেই — কী হিসেবে যোগ করবেন?')->options(CallOnboarding::ROLES)
                ->helperText('"কেন" দেখে আন্দাজ করা হয়েছে, ঠিক না হলে বদলে দিন।')
                ->required($when)->live()->visible($when),
            TextInput::make('onboard_name')->label('নাম')->maxLength(60)
                ->required(fn ($get) => $when($get) && $role($get) !== 'skip')
                ->visible(fn ($get) => $when($get) && $role($get) !== 'skip'),
            Select::make('area_id')->label('এলাকা')->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))->searchable()
                ->required(fn ($get) => $when($get) && in_array($role($get), ['cook', 'customer'], true))
                ->visible(fn ($get) => $when($get) && in_array($role($get), ['cook', 'customer'], true)),
            Select::make('area_ids')->label('কোন এলাকায় কাজ করবেন')->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))->multiple()->searchable()
                ->required(fn ($get) => $when($get) && $role($get) === 'shahidar')
                ->visible(fn ($get) => $when($get) && $role($get) === 'shahidar'),
            TextInput::make('display_name')->label('রান্নাঘরের নাম (না দিলে "নাম-এর রান্নাঘর")')->maxLength(60)
                ->visible(fn ($get) => $when($get) && $role($get) === 'cook'),
            TextInput::make('address')->label('ডেলিভারির ঠিকানা')->maxLength(255)
                ->required(fn ($get) => $when($get) && $role($get) === 'customer')
                ->visible(fn ($get) => $when($get) && $role($get) === 'customer'),
        ];
    }

    /** Runs the choice; returns a line for the notification. */
    private static function onboardFrom(CallRequest $r, array $data): ?string
    {
        if (CallOnboarding::existing($r)) {
            app(CallOnboarding::class)->onboard($r, []);

            return null;
        }
        $role = $data['onboard_role'] ?? null;
        if (! $role) {
            return null;
        }
        $result = app(CallOnboarding::class)->onboard($r, [...$data, 'role' => $role, 'name' => $data['onboard_name'] ?? null]);

        return CallOnboarding::RESULTS[$result] ?? null;
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

    /** The "কেন" cell: a few words; a tap opens the whole reason. */
    public static function reasonColumn(int $chars = 24): TextColumn
    {
        return TextColumn::make('reason')->label('কেন')
            ->formatStateUsing(fn (?string $state) => Str::limit((string) $state, $chars))
            ->tooltip('পুরোটা দেখুন')
            ->color('gray')
            ->action(self::reason());
    }

    /**
     * F70: when they were last talked to, and how many times — tap for the
     * remarks one by one.
     */
    public static function talksColumn(): TextColumn
    {
        return TextColumn::make('talks')->label('শেষ কথা')
            ->state(function (CallRequest $r) {
                $n = count($r->notes ?? []);
                if (! $n) {
                    return 'এখনো হয়নি';
                }

                return Carbon::parse($r->notes[0]['at'])->timezone(DhakaTime::TZ)->format('j M').' · '.Order::banglaDigits($n).' বার';
            })
            ->badge()
            ->color(fn (CallRequest $r) => empty($r->notes) ? 'gray' : 'info')
            ->action(Action::make('talks')
                ->modalHeading(fn (CallRequest $r) => 'কথা হয়েছে '.Order::banglaDigits(count($r->notes ?? [])).' বার — '.($r->name ?: $r->phone))
                ->modalWidth('lg')
                ->schema(fn (CallRequest $r) => [
                    Placeholder::make('history')->hiddenLabel()
                        ->content(fn () => empty($r->notes) ? 'এখনো কোনো রিমার্কস নেই।' : self::history($r)),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('বন্ধ'));
    }

    public static function reason(): Action
    {
        return Action::make('reason')
            ->modalHeading(fn (CallRequest $r) => 'কেন — '.($r->name ?: ($r->user?->name ?: $r->phone)))
            ->modalWidth('lg')
            ->schema(fn (CallRequest $r) => [
                Placeholder::make('full')->hiddenLabel()->content(fn () => new HtmlString('<div style="white-space:pre-line;line-height:1.6">'.e($r->reason).'</div>')),
                Placeholder::make('history')->label('রিমার্কস')
                    ->content(fn () => self::history($r))
                    ->visible(fn () => ! empty($r->notes)),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('বন্ধ');
    }

    public static function history(CallRequest $r): HtmlString
    {
        $rows = collect($r->notes ?? [])->map(fn (array $n) => '<div style="margin-bottom:.4rem"><div>'.e($n['body']).'</div><div style="font-size:.75rem;opacity:.7">'
            .e(Carbon::parse($n['at'])->timezone(DhakaTime::TZ)->format('j M, g:i A')).($n['by'] ? ' · '.e($n['by']) : '').'</div></div>');

        return new HtmlString($rows->implode(''));
    }
}
