<?php

namespace App\Filament\Resources\Kitchens\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KitchenInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('রান্নাঘর')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('display_name')->label('নাম'),
                        TextEntry::make('status')->label('অবস্থা')->badge(),
                        TextEntry::make('user.name')->label('গৃহিণী')->placeholder('—'),
                        TextEntry::make('user.phone')->label('ফোন')->copyable(),
                        TextEntry::make('area.name')->label('এলাকা')->placeholder('এখনো ঠিক করা হয়নি'),
                        TextEntry::make('location')
                            ->label('বাসার লোকেশন')
                            ->state(fn ($record) => $record->location ? 'ম্যাপে দেখুন' : 'এখনো দেওয়া হয়নি')
                            ->url(fn ($record) => $record->location
                                ? "https://www.google.com/maps?q={$record->location->lat},{$record->location->lng}"
                                : null, shouldOpenInNewTab: true),
                        TextEntry::make('rejection_reason')->label('কারণ')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('approver.name')->label('সিদ্ধান্ত দিয়েছেন')->placeholder('—'),
                        TextEntry::make('approved_at')->label('অনুমোদনের সময়')->dateTime()->placeholder('—'),
                    ]),
                Section::make('যোগাযোগ')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('allow_call')->label('কল')->boolean(),
                        IconEntry::make('allow_message')->label('মেসেজ')->boolean(),
                        IconEntry::make('allow_comment')->label('কমেন্ট')->boolean(),
                        TextEntry::make('contact_phone')->label('যোগাযোগের নম্বর')->placeholder('গৃহিণীর নিজের নম্বর'),
                        TextEntry::make('contact_name')->label('যোগাযোগের নাম')->placeholder('—'),
                    ]),
            ]);
    }
}
