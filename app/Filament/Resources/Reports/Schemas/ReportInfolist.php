<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Comment;
use App\Models\Post;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('রিপোর্ট')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reason')->label('কারণ'),
                        TextEntry::make('status')->label('অবস্থা')->badge(),
                        TextEntry::make('details')->label('বিস্তারিত')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('reporter.phone')->label('যিনি জানিয়েছেন')->placeholder('—'),
                        TextEntry::make('created_at')->label('কখন')->dateTime(),
                        TextEntry::make('reportable')
                            ->label('যা নিয়ে অভিযোগ')
                            ->columnSpanFull()
                            ->state(fn ($record) => match (true) {
                                $record->reportable instanceof Post => 'পোস্ট: '.$record->reportable->title,
                                $record->reportable instanceof Comment => 'কমেন্ট: '.$record->reportable->body,
                                $record->reportable !== null => 'ইউজার: '.$record->reportable->phone,
                                default => 'মুছে ফেলা হয়েছে',
                            })
                            ->url(fn ($record) => $record->reportable instanceof Post
                                ? PostResource::getUrl('view', ['record' => $record->reportable])
                                : null),
                        TextEntry::make('resolver.name')->label('সিদ্ধান্ত দিয়েছেন')->placeholder('—'),
                        TextEntry::make('resolved_at')->label('সিদ্ধান্তের সময়')->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
