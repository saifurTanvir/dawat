<?php

namespace App\Filament\Resources\ShahidarApplications\Pages;

use App\Filament\Resources\ShahidarApplications\ShahidarApplicationResource as R;
use Filament\Resources\Pages\ViewRecord;

class ViewShahidarApplication extends ViewRecord
{
    protected static string $resource = R::class;

    protected function getHeaderActions(): array
    {
        return [R::callAction(), R::whatsappAction(), R::contactedAction(), R::onboardAction(), R::rejectAction(), R::reopenAction()];
    }
}
