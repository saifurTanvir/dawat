<?php

namespace App\Filament\Resources\Kitchens\RelationManagers;

use App\Filament\Support\Links;
use App\Models\BoxReview;
use App\Models\Kitchen;
use App\Services\Reviews\BoxReviews;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** F47: customers' reviews of this kitchen's boxes, with names (admin only). */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'রিভিউ';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Kitchen) {
            return null;
        }

        return $ownerRecord->rating_count ? '★ '.number_format((float) $ownerRecord->rating_avg, 1).' ('.$ownerRecord->rating_count.')' : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order', 'user', 'replier', 'kitchen']))
            ->recordUrl(fn (BoxReview $r) => Links::order($r->order))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('কবে')->since(),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী')->url(fn (BoxReview $r) => Links::kitchen($r->kitchen_id))
                    ->visible(fn () => ! $this->getOwnerRecord() instanceof Kitchen),
                TextColumn::make('user.name')->label('গ্রাহক')->url(fn (BoxReview $r) => Links::person($r->user))->description(fn (BoxReview $r) => $r->reviewerLabel().' · '.$r->user?->phone),
                TextColumn::make('order.title')->label('খাবার')->description(fn (BoxReview $r) => 'বক্স '.$r->order?->code())->wrap(),
                TextColumn::make('stars')->label('তারা')->formatStateUsing(fn (BoxReview $r) => $r->stars())
                    ->color(fn (int $state) => $state <= 2 ? 'danger' : ($state >= 4 ? 'success' : 'warning')),
                TextColumn::make('body')->label('লেখা')->placeholder('—')->wrap(),
                TextColumn::make('reply')->label('উত্তর')->placeholder('—')->wrap()
                    ->description(fn (BoxReview $r) => $r->reply ? $r->replyLabel().($r->reply_hidden ? ' · লুকানো' : '') : null),
            ])
            ->filters([
                SelectFilter::make('stars')->label('তারা')->options([1 => '১', 2 => '২', 3 => '৩', 4 => '৪', 5 => '৫']),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label(fn (BoxReview $r) => $r->reply ? 'উত্তর বদলান' : 'উত্তর দিন')
                    ->icon(Heroicon::OutlinedChatBubbleLeft)
                    ->fillForm(fn (BoxReview $r) => ['reply' => $r->reply])
                    ->schema([Textarea::make('reply')->label('উত্তর (গ্রাহক দেখবেন, "দাওয়াত টিম" নামে)')->required()->minLength(2)->maxLength(500)])
                    ->action(function (BoxReview $r, array $data) {
                        app(BoxReviews::class)->reply($r, auth()->user(), 'admin', $data['reply']);
                        Notification::make()->title('উত্তর দেওয়া হয়েছে')->success()->send();
                    }),
                Action::make('hideReply')
                    ->label(fn (BoxReview $r) => $r->reply_hidden ? 'উত্তর দেখান' : 'উত্তর লুকান')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (BoxReview $r) => filled($r->reply))
                    ->action(function (BoxReview $r) {
                        $r->forceFill(['reply_hidden' => ! $r->reply_hidden])->save();
                        Notification::make()->title($r->reply_hidden ? 'উত্তর লুকানো হয়েছে' : 'উত্তর আবার দেখাচ্ছে')->success()->send();
                    }),
            ]);
    }
}
