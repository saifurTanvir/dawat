<?php

namespace App\Filament\Resources\Kitchens\Schemas;

use App\Models\Kitchen;
use App\Services\Feed\PostReach;
use App\Support\ContactHours;
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
                        TextEntry::make('call_now')->label('কল করা যাবে?')
                            ->state(fn (Kitchen $record) => ContactHours::adminLine($record->user))
                            ->url(fn (Kitchen $record) => ContactHours::whatsappUrl($record->user->phone), shouldOpenInNewTab: true)
                            ->helperText('ক্লিক করলে হোয়াটসঅ্যাপ খুলবে — ফ্রি মেসেজ বা কল।'),
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
                Section::make('কোথায় দেখাবে (পরিধি)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reach_m')->label('পরিধি')
                            ->state(fn ($record) => $record->reach_m ? PostReach::REACH_OPTIONS[$record->reach_m] ?? PostReach::km($record->reach_m) : 'স্বাভাবিক — ক্রেতা নিজে দূরত্ব বাছেন'),
                        TextEntry::make('reachAreas.name')->label('আরও যেসব এলাকায় দেখাবে')->badge()->placeholder('—'),
                    ]),
                Section::make('যোগাযোগ')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('allow_call')->label('কল')->boolean(),
                        IconEntry::make('allow_message')->label('মেসেজ')->boolean(),
                        IconEntry::make('allow_comment')->label('কমেন্ট')->boolean(),
                        TextEntry::make('contact_phone')->label('যোগাযোগের নম্বর')->placeholder('গৃহিণীর নিজের নম্বর'),
                        TextEntry::make('contact_name')->label('যোগাযোগের নাম')->placeholder('—'),
                        TextEntry::make('contact_verified_via')->label('যেভাবে যাচাই')
                            ->formatStateUsing(fn ($state) => $state === 'otp' ? 'OTP কোড' : 'অ্যাডমিন')
                            ->placeholder('—'),
                        TextEntry::make('pending_contact_phone')->label('অপেক্ষমাণ নতুন নম্বর')
                            ->formatStateUsing(fn ($state, $record) => "{$record->pending_contact_name}: {$state}")
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
