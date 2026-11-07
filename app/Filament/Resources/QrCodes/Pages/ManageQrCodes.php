<?php

namespace App\Filament\Resources\QrCodes\Pages;

use App\Filament\Resources\QrCodes\QrCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQrCodes extends ManageRecords
{
    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
