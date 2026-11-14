<?php

namespace App\Filament\Resources\Kitchens\Schemas;

use App\Filament\Support\FeeFields;
use App\Filament\Support\ReachFields;
use App\Support\PhoneNumber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Admins fix details on a cook's behalf; status changes go through the moderation actions. */
class KitchenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('রান্নাঘর')
                    ->schema([
                        TextInput::make('display_name')->label('নাম')->required()->maxLength(60),
                        Select::make('area_id')->label('এলাকা')->relationship('area', 'name'),
                        TextInput::make('pickup_address')->label('পিকআপের ঠিকানা')->maxLength(255)
                            ->helperText('শাহিদার এই ঠিকানা থেকে নিয়মিত গ্রাহকের খাবার তুলবেন। ক্রেতারা কখনো দেখেন না।'),
                        TextInput::make('bfsa_reg_no')->label('BFSA রেজিস্ট্রেশন নম্বর')->maxLength(50),
                    ]),
                // F56
                FeeFields::section('শুধু এই গৃহিণীর জন্য। খালি রাখলে এলাকার নিয়ম, এলাকারও না থাকলে "সবার জন্য" নিয়ম চলবে। নতুন বক্স থেকে লাগবে।'),
                Section::make('কোথায় দেখাবে (পরিধি)')
                    ->description('এই রান্নাঘরের পোস্ট কতদূর পর্যন্ত দেখাবে। বদলালে আজকের চালু পোস্ট আর পরের সব পোস্টে লাগবে।')
                    ->schema(ReachFields::make(relationship: true)),
                Section::make('যোগাযোগ')
                    ->description('পোস্টে কোন বাটন দেখাবে, আর ক্রেতা কোন নম্বরে যোগাযোগ করবে।')
                    ->schema([
                        Toggle::make('allow_call')->label('কল করা যাবে'),
                        Toggle::make('allow_message')->label('মেসেজ করা যাবে'),
                        Toggle::make('allow_comment')->label('কমেন্ট করা যাবে'),
                        Toggle::make('allow_order')->label('"নিতে চাই" দিয়ে অর্ডার করা যাবে'),
                        TextInput::make('contact_phone')
                            ->label('যোগাযোগের নম্বর (খালি রাখলে গৃহিণীর নিজের নম্বর)')
                            ->tel()
                            ->regex(PhoneNumber::PATTERN)
                            ->dehydrateStateUsing(fn (?string $state) => PhoneNumber::normalize($state)),
                        TextInput::make('contact_name')->label('যোগাযোগের নাম (যেমন শাহিদারের নাম)')->maxLength(60),
                    ]),
            ]);
    }
}
