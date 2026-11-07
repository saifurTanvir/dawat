<?php

namespace App\Filament\Resources\Shahidars\Pages;

use App\Filament\Resources\Shahidars\ShahidarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShahidars extends ManageRecords
{
    protected static string $resource = ShahidarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('নতুন শাহিদার')
                ->schema(ShahidarResource::createForm())
                ->using(fn (array $data) => ShahidarResource::createShahidar($data)),
        ];
    }
}
