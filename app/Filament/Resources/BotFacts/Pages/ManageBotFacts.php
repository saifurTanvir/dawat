<?php

namespace App\Filament\Resources\BotFacts\Pages;

use App\Filament\Resources\BotFacts\BotFactResource;
use App\Models\BotFact;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageBotFacts extends ManageRecords
{
    protected static string $resource = BotFactResource::class;

    public function getSubheading(): ?string
    {
        return 'Messenger-এর বট প্রতিটা উত্তরের আগে এই তালিকা পড়ে, আর এর বাইরে কিছু বলে না। হোয়াটসঅ্যাপের বট এলে সেও এই তালিকাই পড়বে। নতুন ফিচার এলে এখানে যোগ হবে।';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('নতুন তথ্য'),
            Action::make('preview')
                ->label('বট যা পড়ে')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading('বটের কাছে যা যায় (চালু তথ্যগুলো)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('বন্ধ')
                ->modalContent(fn () => new HtmlString('<pre style="white-space:pre-wrap;font-size:13px;line-height:1.6">'.e(BotFact::promptText()).'</pre>')),
        ];
    }
}
