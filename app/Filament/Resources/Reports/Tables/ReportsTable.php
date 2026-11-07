<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\ReportStatus;
use App\Filament\Actions\ReportActions;
use App\Models\Comment;
use App\Models\Post;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reportable_type')->label('কী নিয়ে')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Post::class => 'পোস্ট',
                        Comment::class => 'কমেন্ট',
                        default => 'ইউজার',
                    }),
                TextColumn::make('reason')->label('কারণ'),
                TextColumn::make('reporter.phone')->label('যিনি জানিয়েছেন')->placeholder('—'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('created_at')->label('কখন')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(ReportStatus::class)->default(ReportStatus::Open->value),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReportActions::make()),
            ]);
    }
}
