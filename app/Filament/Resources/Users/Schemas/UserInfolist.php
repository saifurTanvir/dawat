<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\Links;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
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
                // F49: this person's other pages, and their history as a buyer.
                Section::make('কোথায় কোথায় আছেন')->columns(3)->schema([
                    TextEntry::make('kitchen_link')->label('গৃহিণী')->state(fn (User $record) => $record->kitchen ? $record->kitchen->display_name.' →' : null)
                        ->url(fn (User $record) => Links::kitchen($record->kitchen?->id))->placeholder('—'),
                    TextEntry::make('customer_link')->label('নিয়মিত গ্রাহক')->state(fn (User $record) => $record->customer ? 'গ্রাহকের পাতা →' : null)
                        ->url(fn (User $record) => $record->customer ? CustomerResource::getUrl('view', ['record' => $record->customer]) : null)->placeholder('—'),
                    TextEntry::make('shahidar_link')->label('শাহিদার')->state(fn (User $record) => $record->isShahidar() ? 'শাহিদারের পাতা →' : null)
                        ->url(fn (User $record) => $record->isShahidar() ? Links::shahidar($record->id) : null)->placeholder('—'),
                ]),
                Section::make('গ্রাহক হিসেবে সারাংশ')->columnSpanFull()->schema([ViewEntry::make('history_summary')->hiddenLabel()->view('filament.history.summary-customer')]),
            ]);
    }
}
