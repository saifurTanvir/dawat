<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\LoginHelpActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Actions\ViewAsAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAsAction::make(fn ($record) => $record->user, 'গ্রাহকের অ্যাপ দেখুন'),
            LoginHelpActions::group(fn ($record) => $record->user),
            CustomerResource::pairAction(),
            CustomerResource::bookNowAction(),
            CustomerResource::issueBillAction(),
            CustomerResource::pauseAction(),
            CustomerResource::stopAction(),
            RemovalActions::unpair(),
            RemovalActions::restart(),
            RemovalActions::deleteCustomer(),
        ];
    }
}
