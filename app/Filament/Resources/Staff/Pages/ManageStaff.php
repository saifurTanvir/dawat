<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected ?string $subheading = 'স্টাফ ইমেইল আর পাসওয়ার্ড দিয়ে অ্যাডমিন প্যানেলে ঢুকবেন, আর শুধু তাঁর ভূমিকার কাজগুলো দেখবেন।';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('নতুন স্টাফ')
                ->using(fn (array $data) => StaffResource::save(null, $data)),
        ];
    }
}
