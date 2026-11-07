<?php

namespace App\Filament\Resources\Stickers\Pages;

use App\Filament\Resources\Stickers\StickerResource;
use App\Models\Sticker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
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
                    Select::make('pages')->label('কয় পাতা (প্রতি পাতায় ৩২টা)')->options([1 => '১ পাতা · ৩২টা', 2 => '২ পাতা · ৬৪টা', 4 => '৪ পাতা · ১২৮টা'])->default(1)->required(),
                ])
                ->action(function (array $data) {
                    $batch = now(config('app.display_timezone'))->format('ymd-His');
                    DB::transaction(function () use ($data, $batch) {
                        foreach (range(1, 32 * (int) $data['pages']) as $i) {
                            Sticker::create(['code' => Sticker::newCode(), 'batch' => $batch]);
                        }
                    });

                    return redirect()->route('filament.admin.stickers.sheet', $batch);
                }),
        ];
    }
}
