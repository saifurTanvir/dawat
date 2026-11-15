<?php

namespace App\Filament\Resources\Stickers\Pages;

use App\Filament\Resources\Stickers\StickerResource;
use App\Http\Controllers\Admin\StickerSheetController;
use App\Models\Sticker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManageStickers extends ManageRecords
{
    protected static string $resource = StickerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newSheet')
                ->label('নতুন স্টিকার শিট')
                ->icon(Heroicon::OutlinedPrinter)
                ->schema([
                    Select::make('paper')->label('কোথায় প্রিন্ট')->options(StickerSheetController::PAPERS)->default('thermal')->required()->live(),
                    Select::make('pages')
                        ->label(fn (Get $get) => $get('paper') === 'a4' ? 'কয় পাতা' : 'কয়টা লেবেল')
                        ->options(fn (Get $get) => $get('paper') === 'a4'
                            ? [1 => '১ পাতা · ৩২টা স্টিকার', 2 => '২ পাতা · ৬৪টা', 4 => '৪ পাতা · ১২৮টা']
                            : [5 => '৫টা লেবেল · ২০টা স্টিকার', 10 => '১০টা লেবেল · ৪০টা', 25 => '২৫টা লেবেল · ১০০টা', 50 => '৫০টা লেবেল · ২০০টা'])
                        ->default(10)->required(),
                ])
                ->action(function (array $data) {
                    $paper = $data['paper'] ?? 'thermal';
                    $count = StickerSheetController::PER_PAGE[$paper] * min((int) $data['pages'], 50);
                    $batch = now(config('app.display_timezone'))->format('ymd-His');
                    DB::transaction(function () use ($count, $batch) {
                        foreach (range(1, $count) as $i) {
                            Sticker::create(['code' => Sticker::newCode(), 'batch' => $batch]);
                        }
                    });

                    return redirect()->route('filament.admin.stickers.sheet', ['batch' => $batch, 'paper' => $paper]);
                }),
        ];
    }
}
