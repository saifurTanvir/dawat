<?php

namespace App\Filament\Resources\CallRequests\Pages;

use App\Filament\Resources\CallRequests\CallRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCallRequests extends ManageRecords
{
    protected static string $resource = CallRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('নতুন যোগ করুন')
                ->mutateDataUsing(fn (array $data) => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
