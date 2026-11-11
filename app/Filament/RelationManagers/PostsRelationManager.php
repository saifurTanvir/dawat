<?php

namespace App\Filament\RelationManagers;

use App\Actions\PublishPost;
use App\Enums\MealSlot;
use App\Filament\Support\CsvDownload;
use App\Filament\Support\Links;
use App\Models\Post;
use App\Services\Posts\PlateSplit;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** F49: a kitchen's posts and where their plates went. */
class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'পোস্ট';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $split = fn (Post $p) => PlateSplit::line(app(PlateSplit::class)->forPost($p));

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['orders', 'postedBy']))
            ->defaultSort('published_at', 'desc')
            ->recordUrl(fn (Post $record) => Links::post($record->id))
            ->columns([
                TextColumn::make('published_at')->label('কবে')->dateTime('j M Y, g:i a', config('app.display_timezone'))->sortable()
                    ->description(fn (Post $p) => $p->meal_slot->label()),
                TextColumn::make('title')->label('খাবার')->wrap(),
                TextColumn::make('price_paisa')->label('দাম')->formatStateUsing(fn (int $state) => '৳'.number_format($state / 100)),
                TextColumn::make('plates_total')->label('প্লেটের ভাগ')->state($split)->description(fn (Post $p) => 'মোট '.$p->plates_total)->wrap(),
                TextColumn::make('source')->label('কে দিয়েছেন')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state, Post $p) => (PublishPost::SOURCES[$state ?? 'cook'] ?? '').($p->postedBy && $state !== 'cook' ? ' · '.$p->postedBy->name : '')),
                TextColumn::make('status')->label('অবস্থা')->badge(),
            ])
            ->filters([SelectFilter::make('meal_slot')->label('বেলা')->options(MealSlot::class)])
            ->headerActions([CsvDownload::make('posts', [
                'কবে' => fn (Post $p) => $p->published_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
                'বেলা' => fn (Post $p) => $p->meal_slot->label(),
                'খাবার' => fn (Post $p) => $p->title,
                'দাম' => fn (Post $p) => intdiv($p->price_paisa, 100),
                'মোট প্লেট' => fn (Post $p) => $p->plates_total,
                'প্লেটের ভাগ' => $split,
                'কে দিয়েছেন' => fn (Post $p) => PublishPost::SOURCES[$p->source ?? 'cook'] ?? '',
            ], ['orders'])]);
    }
}
