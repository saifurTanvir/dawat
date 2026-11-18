<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanies extends ManageRecords
{
    protected static string $resource = CompanyResource::class;

    protected ?string $subheading = 'অফিসের কর্মীরা নিয়মিত গ্রাহক — গ্রাহকের ফর্মে অফিস বেছে দিন। এক অফিসের কর্মীরা একই গৃহিণীর সাথে জোড়া হতে পারেন; সবার বক্স একসাথে "অফিস ড্রপ" হয়ে যায়।';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('নতুন অফিস')
                ->mutateDataUsing(fn (array $data) => CompanyResource::withContact($data)),
        ];
    }
}
