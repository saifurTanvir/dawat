<?php

namespace App\Filament\Resources\MessengerThreads;

use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\MessengerThreads\Pages\ManageMessengerThreads;
use App\Filament\Support\FeatureGate;
use App\Jobs\AnswerMessenger;
use App\Models\MessengerThread;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * F80: every Messenger chat the server has seen. The bot answers by itself;
 * here an admin reads a chat, answers from the panel, or turns the bot off for it.
 */
class MessengerThreadResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = MessengerThread::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'রাঁধুনি ও খাবার';

    protected static ?string $modelLabel = 'Messenger চ্যাট';

    protected static ?string $pluralModelLabel = 'Messenger চ্যাট';

    protected static ?string $slug = 'messenger';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $n = MessengerThread::query()->where('needs_human', true)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['lastMessage', 'kitchen']))
            ->defaultSort('last_in_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('name')->label('মানুষ')->weight('semibold')->searchable()
                    ->state(fn (MessengerThread $t) => $t->name ?: ($t->fact('name') ?: 'নাম জানা নেই'))
                    ->description(fn (MessengerThread $t) => collect([$t->name ? $t->fact('name') : null, $t->fact('phone')])->filter()->implode(' · ') ?: null),
                TextColumn::make('lastMessage.text')->label('শেষ মেসেজ')->wrap()->limit(90)->grow()
                    ->formatStateUsing(fn ($state, MessengerThread $t) => ($t->lastMessage?->direction === 'out' ? 'আমরা: ' : '').Str::limit((string) $state, 90))
                    ->placeholder('—'),
                TextColumn::make('state')->label('অবস্থা')->badge()
                    ->state(fn (MessengerThread $t) => $t->stateLabel())
                    ->color(fn (MessengerThread $t) => match (true) {
                        $t->needs_human => 'danger',
                        $t->bot_off, (bool) $t->paused_until?->isFuture() => 'warning',
                        default => 'success',
                    })
                    ->description(fn (MessengerThread $t) => $t->needs_human ? $t->human_reason : ($t->send_error ? 'পাঠানো যায়নি: '.$t->send_error : null)),
                TextColumn::make('kitchen.display_name')->label('রান্নাঘর')->placeholder('—')
                    ->url(fn (MessengerThread $t) => $t->kitchen ? KitchenResource::getUrl('view', ['record' => $t->kitchen]) : null),
                TextColumn::make('last_in_at')->label('শেষ মেসেজ এসেছে')->since()->sortable(),
            ])
            ->filters([
                Filter::make('needs_human')->label('মানুষ লাগবে')->query(fn ($query) => $query->where('needs_human', true)),
                Filter::make('waiting')->label('উত্তর বাকি')->query(fn ($query) => $query->waiting()),
            ])
            ->recordAction('chat')
            ->recordActions([
                self::chat(),
                Action::make('botAnswer')->label('বট দিয়ে উত্তর')->icon(Heroicon::OutlinedSparkles)->color('success')
                    ->visible(fn (MessengerThread $t) => Graph::botOn() && MessengerThread::query()->answerable()->whereKey($t->id)->exists())
                    ->action(function (MessengerThread $t) {
                        AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'));
                        Notification::make()->title('বট উত্তর লিখছে — কয়েক সেকেন্ড পর "চ্যাট" খুলে দেখুন')->success()->send();
                    }),
                Action::make('seen')->label('দেখেছি')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (MessengerThread $t) => $t->needs_human)
                    ->action(fn (MessengerThread $t) => $t->forceFill(['needs_human' => false, 'human_reason' => null])->save()),
                // Meta will not deliver to some chats (e.g. #551, a comment with no inbox): close them by hand.
                Action::make('close')->label('বন্ধ করুন')->icon(Heroicon::OutlinedXCircle)->color('gray')
                    ->visible(fn (MessengerThread $t) => filled($t->send_error) && MessengerThread::query()->waiting()->whereKey($t->id)->exists())
                    ->requiresConfirmation()->modalDescription('Meta এই চ্যাটে মেসেজ পৌঁছাতে দিচ্ছে না। চ্যাটটা "উত্তর বাকি" থেকে সরিয়ে রাখবে।')
                    ->action(fn (MessengerThread $t) => $t->forceFill(['last_out_at' => now(), 'needs_human' => false, 'human_reason' => null])->save()),
                Action::make('bot')->label(fn (MessengerThread $t) => $t->botMayAnswer() ? 'বট বন্ধ' : 'বট চালু')
                    ->icon(Heroicon::OutlinedCpuChip)->color('gray')
                    ->action(fn (MessengerThread $t) => $t->botMayAnswer()
                        ? $t->forceFill(['bot_off' => true])->save()
                        : $t->forceFill(['bot_off' => false, 'paused_until' => null])->save()),
            ]);
    }

    /** Read the chat; answer from here (the bot then waits a few hours). */
    public static function chat(): Action
    {
        return Action::make('chat')->label('চ্যাট')->icon(Heroicon::OutlinedChatBubbleOvalLeft)
            ->modalHeading(fn (MessengerThread $t) => $t->name ?: 'Messenger চ্যাট')
            ->modalSubmitActionLabel('পাঠান')
            ->schema(fn (MessengerThread $t) => [
                ViewField::make('history')->hiddenLabel()->view('filament.messenger.chat')
                    ->viewData(['messages' => $t->messages()->latest('id')->limit(40)->get()->reverse(), 'thread' => $t]),
                Textarea::make('reply')->label('উত্তর লিখুন (ইচ্ছা হলে)')->rows(3)->maxLength(1900)
                    ->helperText('পাঠালে এই চ্যাটে বট '.MessengerThread::PAUSE_HOURS.' ঘণ্টা চুপ থাকবে। শেষ মেসেজের ২৪ ঘণ্টা পর Meta আর পাঠাতে দেয় না।'),
            ])
            ->action(function (MessengerThread $t, array $data) {
                $text = trim((string) ($data['reply'] ?? ''));
                if ($text === '') {
                    return;
                }
                if (! Graph::configured()) {
                    Notification::make()->title('Messenger এখনো যুক্ত হয়নি')->danger()->send();

                    return;
                }
                if ($error = app(Graph::class)->send($t->psid, $text)) {
                    Notification::make()->title('পাঠানো যায়নি')->body($error)->danger()->send();

                    return;
                }
                $t->messages()->create(['direction' => 'out', 'by' => 'admin', 'text' => $text]);
                $t->forceFill(['last_out_at' => now(), 'paused_until' => now()->addHours(MessengerThread::PAUSE_HOURS), 'needs_human' => false, 'human_reason' => null])->save();
                Notification::make()->title('পাঠানো হয়েছে')->success()->send();
            });
    }

    public static function botApprover(): ?string
    {
        return Agent::approver()?->name;
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessengerThreads::route('/')];
    }
}
