<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ইউজার')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')->label('ফোন')->copyable(),
                        TextEntry::make('name')->label('নাম')->placeholder('—'),
                        TextEntry::make('roles.name')->label('রোল')->badge()->color('gray'),
                        TextEntry::make('status')->label('অবস্থা')->badge(),
                        TextEntry::make('kitchen.display_name')->label('রান্নাঘর')->placeholder('—'),
                        TextEntry::make('email')->label('ইমেইল (অ্যাডমিন)')->placeholder('—'),
                        TextEntry::make('phone_verified_at')->label('নম্বর যাচাই')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->label('যোগ দিয়েছেন')->dateTime(),
                    ]),
            ]);
    }
}
