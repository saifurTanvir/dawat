<?php

namespace App\Filament\Widgets;

use App\Models\QrCode;
use App\Support\DhakaTime;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** F23: which printed QR codes bring people (and contacts) in. */
class TopQrCodes extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'কোন QR কোড কাজ করছে';

    public function table(Table $table): Table
    {
        $since = DhakaTime::startOfDay(29);

        return $table
            ->query(fn (): Builder => QrCode::query()
                ->with('area')
                ->withCount(['contactEvents as month_contacts' => fn ($q) => $q->where('created_at', '>=', $since)])
                ->orderByDesc('scans'))
            ->paginated([5])
            ->columns([
                TextColumn::make('label')->label('জায়গা')->weight('semibold')->wrap(),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('scans')->label('স্ক্যান (মোট)'),
                TextColumn::make('month_contacts')->label('যোগাযোগ (৩০ দিন)'),
                TextColumn::make('last_scanned_at')->label('শেষ স্ক্যান')->since()->placeholder('—'),
            ])
            ->emptyStateHeading('এখনো কোনো QR কোড নেই');
    }
}
