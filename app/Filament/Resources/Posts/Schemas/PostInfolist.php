<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('খাবার')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('images.path')
                            ->label('ছবি')
                            ->disk(config('filesystems.media_disk', 'public'))
                            ->imageHeight(140)
                            ->columnSpanFull(),
                        TextEntry::make('title')->label('খাবার'),
                        TextEntry::make('kitchen.display_name')->label('রান্নাঘর'),
                        TextEntry::make('price_paisa')->label('দাম')->formatStateUsing(fn (int $state) => '৳'.number_format($state / 100)),
                        TextEntry::make('plates_left')->label('প্লেট')
                            ->formatStateUsing(fn ($record) => "{$record->plates_left} বাকি, মোট {$record->plates_total}"),
                        TextEntry::make('meal_slot')->label('বেলা')->badge(),
                        TextEntry::make('status')->label('অবস্থা')->badge(),
                        TextEntry::make('tags.name')->label('ট্যাগ')->badge()->color('gray')->placeholder('—'),
                        TextEntry::make('available_until')->label('পাওয়া যাবে')->dateTime(),
                        TextEntry::make('note')->label('গৃহিণীর নোট')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
