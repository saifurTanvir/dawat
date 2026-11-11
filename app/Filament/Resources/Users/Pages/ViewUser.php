<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\LoginHelpActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Actions\UserBlockActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [LoginHelpActions::group(fn ($record) => $record), ...UserBlockActions::make(), RemovalActions::deleteUser()];
    }
}
