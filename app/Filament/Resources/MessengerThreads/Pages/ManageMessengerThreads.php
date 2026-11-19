<?php

namespace App\Filament\Resources\MessengerThreads\Pages;

use App\Filament\Resources\MessengerThreads\MessengerThreadResource;
use App\Jobs\AnswerMessenger;
use App\Models\MessengerThread;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Backfill;
use App\Services\Messenger\Brain;
use App\Services\Messenger\Graph;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ManageMessengerThreads extends ManageRecords
{
    protected static string $resource = MessengerThreadResource::class;

    public function getSubheading(): ?string
    {
        $on = Graph::botOn();
        $last = Cache::get('messenger:last-webhook');

        return match (true) {
            ! Graph::configured() => 'Messenger এখনো সার্ভারের সাথে যুক্ত হয়নি। "যুক্ত করার তথ্য" দেখুন।',
            ! $on => 'বট বন্ধ: মেসেজ এখানে জমা হচ্ছে, কিন্তু সার্ভার উত্তর দিচ্ছে না।',
            ! Brain::available() => 'বট চালু, কিন্তু AI-এর key নেই — শুধু প্রথম মেসেজ যাচ্ছে, বাকিগুলো "মানুষ লাগবে"-তে।',
            default => 'বট চালু: মেসেজ এলে কয়েক সেকেন্ডে সার্ভার উত্তর দেয়।',
        }.($last ? ' শেষ মেসেজ এসেছে '.Carbon::parse($last)->diffForHumans().'।' : '');
    }

    protected function getHeaderActions(): array
    {
        $admin = fn () => (bool) auth()->user()?->isAdmin();

        return [
            Action::make('toggle')
                ->visible($admin)
                ->label(fn () => Graph::botOn() ? 'বট বন্ধ করুন' : 'বট চালু করুন')
                ->color(fn () => Graph::botOn() ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedPower)
                ->requiresConfirmation()
                ->modalDescription(fn () => Graph::botOn()
                    ? 'বন্ধ করলে মেসেজ এখানে জমা হবে, কেউ উত্তর পাবেন না যতক্ষণ না আপনি নিজে দেন।'
                    : 'চালু করলে সার্ভার নিজেই সবার উত্তর দেবে। Chrome-এর Messenger উত্তরের কাজটা (scheduled task) আর Business Suite-এর অটো-রিপ্লাই বন্ধ করে দিন, নইলে একজন দুইবার উত্তর পাবেন।')
                ->action(function () {
                    $on = ! Graph::botOn();
                    Graph::setBot($on, auth()->user());
                    Notification::make()->title($on ? 'বট চালু হয়েছে' : 'বট বন্ধ হয়েছে')->success()->send();
                }),
            Action::make('answerWaiting')
                ->visible(fn () => $admin() && Graph::botOn())
                ->label(fn () => 'অপেক্ষার '.MessengerThread::query()->answerable()->count().'টা চ্যাটে উত্তর দিন')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('গত ২৪ ঘণ্টায় যাঁদের শেষ মেসেজের উত্তর যায়নি (বট বা মানুষ কেউ দেননি), বট তাঁদের উত্তর দেবে। ২৪ ঘণ্টার পুরনো চ্যাটে Meta আর পাঠাতে দেয় না।')
                ->action(function () {
                    $n = 0;
                    foreach (MessengerThread::query()->answerable()->get() as $t) {
                        AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'))->delay(now()->addSeconds(2 * $n++));
                    }
                    Notification::make()->title("{$n}টা চ্যাটে উত্তর যাচ্ছে")->success()->send();
                }),
            Action::make('answerNow')
                ->visible(fn () => $admin() && Graph::botOn() && MessengerThread::query()->answerable()->exists())
                ->label('এখনই ৫টার উত্তর দিন')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->action(function () {
                    // Answers here, in this request, beside the queue — so a big backlog clears faster.
                    set_time_limit(120);
                    $n = 0;
                    foreach (MessengerThread::query()->answerable()->orderBy('last_in_at')->limit(15)->get() as $t) {
                        if ($n >= 5) {
                            break;
                        }
                        $lock = Cache::lock("messenger-answer:{$t->id}", 120);
                        if (! $lock->get()) {
                            continue; // the queue is on it
                        }
                        try {
                            $fresh = $t->fresh();
                            if ($fresh->last_out_at && $fresh->last_out_at->gte($fresh->last_in_at)) {
                                continue;
                            }
                            app(Agent::class)->answer($fresh);
                            $n++;
                        } finally {
                            $lock->release();
                        }
                    }
                    Notification::make()->title("{$n}টা চ্যাটে উত্তর গেল")->success()->send();
                }),
            Action::make('backfill')
                ->visible(fn () => $admin() && Graph::configured())
                ->label('আগের চ্যাট আনুন')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('বট চালুর আগের (গত ২৩ ঘণ্টার) যে চ্যাটগুলো এখানে নেই, Meta থেকে এনে রাখবে। যাঁদের উত্তর বাকি, বট চালু থাকলে তাঁদের উত্তর দেবে।')
                ->action(function () {
                    $r = app(Backfill::class)->run();
                    $n = 0;
                    if (Graph::botOn()) {
                        foreach (MessengerThread::query()->answerable()->get() as $t) {
                            AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'))->delay(now()->addSeconds(3 * $n++));
                        }
                    }
                    Notification::make()
                        ->title("{$r['added']}টা আগের চ্যাট আনা হলো, {$r['waiting']}টার উত্তর বাকি ছিল".($n ? " — {$n}টায় উত্তর যাচ্ছে" : ''))
                        ->body($r['error'])
                        ->{$r['error'] ? 'warning' : 'success'}()
                        ->send();
                }),
            Action::make('token')
                ->visible($admin)
                ->label('পেজ টোকেন বদলান')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->modalDescription('Meta-র Graph API Explorer থেকে নেওয়া পেজের টোকেন এখানে দিন। সার্ভারে লুকিয়ে (এনক্রিপ্ট করে) রাখা হবে, কেউ দেখতে পাবে না।')
                ->schema([
                    TextInput::make('token')->label('পেজের টোকেন')->password()->required()->autocomplete('off'),
                ])
                ->action(function (array $data) {
                    $check = Graph::checkToken($data['token']);
                    if (! $check['ok']) {
                        Notification::make()->title('টোকেন কাজ করছে না')->body($check['error'])->danger()->send();

                        return;
                    }
                    Graph::savePageToken($check['token'], auth()->user());
                    Notification::make()->title('টোকেন রাখা হলো — পেজ: '.$check['name'])->success()->send();
                }),
            Action::make('setup')
                ->visible($admin)
                ->label('যুক্ত করার তথ্য')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->modalHeading('Meta-তে Messenger যুক্ত করার তথ্য')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('বন্ধ')
                ->schema([
                    ViewField::make('setup')->hiddenLabel()->view('filament.messenger.setup')->viewData([
                        'url' => route('webhooks.messenger'),
                        'verify' => Graph::verifyToken(),
                        'checks' => [
                            'পেজের টোকেন (MESSENGER_PAGE_TOKEN)' => filled(Graph::pageToken()),
                            'App secret (MESSENGER_APP_SECRET)' => filled(config('services.messenger.app_secret')),
                            'App ID (MESSENGER_APP_ID, ইচ্ছা হলে)' => filled(config('services.messenger.app_id')),
                            'AI-এর key (ANTHROPIC_API_KEY)' => Brain::available(),
                        ],
                        'approver' => MessengerThreadResource::botApprover(),
                    ]),
                ]),
        ];
    }
}
