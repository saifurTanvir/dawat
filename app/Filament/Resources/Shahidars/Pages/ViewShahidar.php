<?php

namespace App\Filament\Resources\Shahidars\Pages;

use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Shahidars\ShahidarResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** F49: a shahidar's history. */
class ViewShahidar extends ViewRecord
{
    protected static string $resource = ShahidarResource::class;

    public function getTitle(): string
    {
        return 'শাহিদার: '.($this->getRecord()->name ?: $this->getRecord()->phone);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), RemovalActions::removeShahidar()];
    }
}
