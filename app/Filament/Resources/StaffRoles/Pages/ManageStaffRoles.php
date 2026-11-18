<?php

namespace App\Filament\Resources\StaffRoles\Pages;

use App\Filament\Resources\StaffRoles\StaffRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffRoles extends ManageRecords
{
    protected static string $resource = StaffRoleResource::class;

    protected ?string $subheading = 'ভূমিকা বানিয়ে ঠিক করুন সে কোন কাজগুলো দেখবে, তারপর "স্টাফ" পাতায় মানুষ যোগ করে ভূমিকা দিন।';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('নতুন ভূমিকা')
                ->using(fn (array $data) => StaffRoleResource::save(null, $data)),
        ];
    }
}
