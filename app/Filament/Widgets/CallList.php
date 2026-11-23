<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\CallRequestActions;
use App\Filament\Resources\CallRequests\CallRequestResource;
use App\Models\CallRequest;
use App\Support\AdminFeatures;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** F66: on top of the dashboard — people waiting for the owner's call. */
class CallList extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'যোগাযোগ করতে হবে';

    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class) && (CallRequest::open()->exists());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CallRequest::query()->open()->with('user'))
            ->defaultSort('created_at')
            ->paginated([10])
            ->headerActions([
                Action::make('all')->label('সব দেখুন')->link()->url(CallRequestResource::getUrl()),
            ])
            ->columns([
                TextColumn::make('name')->label('কে')->weight('semibold')
                    ->state(fn (CallRequest $r) => $r->name ?: ($r->user?->name ?: $r->phoneLabel()))
                    ->description(fn (CallRequest $r) => $r->phoneLabel()),
                CallRequestActions::reasonColumn(),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                CallRequestActions::talksColumn(),
            ])
            ->recordActions(CallRequestActions::row());
    }
}
