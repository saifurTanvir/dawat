<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Actions\ReportActions;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return ReportActions::make();
    }
}
