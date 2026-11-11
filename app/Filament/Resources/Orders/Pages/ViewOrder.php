<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Actions\ActForCookActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

/** F49: one box — what happened, who, and the money. */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'বক্স '.$this->getRecord()->code().' · '.$this->getRecord()->service_date->format('j M Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::acceptAction(),
            OrderResource::cancelAction(),
            ActForCookActions::decline(),
            ActForCookActions::handed(),
            RemovalActions::deleteOrder(),
        ];
    }
}
