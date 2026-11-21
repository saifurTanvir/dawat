<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\RemarkActions;
use App\Filament\Support\Links;
use App\Models\Remark;
use App\Support\AdminFeatures;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** F63: remarks whose follow-up day has come. */
class FollowUps extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'আজকের ফলো-আপ';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && (Remark::query()->due()->exists());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Remark::query()->due()->with(['user.roles', 'user.kitchen', 'author']))
            ->defaultSort('follow_up_on')
            ->paginated([10])
            ->columns([
                TextColumn::make('who')->label('কে')->weight('semibold')
                    ->state(fn (Remark $r) => RemarkActions::who($r->user))
                    ->description(fn (Remark $r) => $r->user->phone.' · '.$r->user->roles->pluck('name')->map(fn ($n) => ['cook' => 'গৃহিণী', 'buyer' => 'ক্রেতা', 'shahidar' => 'এলাকা ম্যানেজার', 'admin' => 'অ্যাডমিন'][$n] ?? $n)->implode(', '))
                    ->url(fn (Remark $r) => Links::person($r->user)),
                TextColumn::make('body')->label('রিমার্কস')->wrap(),
                TextColumn::make('follow_up_on')->label('কবে')->date('j M')
                    ->description(fn (Remark $r) => ($r->author?->name ?? '').' লিখেছেন'),
            ])
            ->recordActions([
                Action::make('call')->label('কল')->icon(Heroicon::OutlinedPhone)->color('gray')
                    ->url(fn (Remark $r) => 'tel:+'.PhoneNumber::international($r->user->phone)),
                RemarkActions::add(fn (Remark $r) => $r->user, 'addRemark', closeByDefault: true),
                RemarkActions::close(),
            ]);
    }
}
