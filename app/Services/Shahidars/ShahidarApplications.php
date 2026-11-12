<?php

namespace App\Services\Shahidars;

use App\Enums\ShahidarApplicationStatus;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Models\ShahidarApplication;
use App\Models\User;
use App\Services\Push\Push;
use App\Support\Roles;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** F53: applications to work as a shahidar, from the form to onboarding. */
class ShahidarApplications
{
    /**
     * Saves the form. A second form from the same number while the first is still
     * open updates it, so admins see one row per person.
     *
     * @return array{0: ShahidarApplication, 1: bool} the application, and whether it was new
     */
    public function submit(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $fields = Arr::except($data, ['area_ids', 'agree']);
            $existing = ShahidarApplication::query()->open()->where('phone', $data['phone'])->latest()->first();

            if ($existing) {
                $existing->update($fields);
                $application = $existing;
            } else {
                $application = ShahidarApplication::create([...$fields, 'status' => ShahidarApplicationStatus::New]);
            }
            $application->areas()->sync($data['area_ids']);

            if (! $existing) {
                $areas = $application->areas()->pluck('name')->implode(', ');
                User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser(
                    $id,
                    'নতুন শাহিদার আবেদন',
                    "{$application->name} · {$areas}",
                    '/admin/shahidar-applications/'.$application->id,
                    'shahidar-application-'.$application->id,
                ));
            }

            return [$application, ! $existing];
        });
    }

    public function isAlreadyShahidar(string $phone): bool
    {
        return User::query()->where('phone', $phone)->role(Roles::SHAHIDAR)->exists();
    }

    public function mark(ShahidarApplication $application, ShahidarApplicationStatus $status, User $admin, ?string $note = null): void
    {
        $application->update([
            'status' => $status,
            'admin_note' => $note ?? $application->admin_note,
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);
    }

    /** Makes them a shahidar (or adds the role to their account) for the chosen areas. */
    public function onboard(ShahidarApplication $application, User $admin, array $areaIds, ?string $name = null): User
    {
        return DB::transaction(function () use ($application, $admin, $areaIds, $name) {
            $user = ShahidarResource::createShahidar([
                'phone' => $application->phone,
                'name' => $name ?: $application->name,
                'area_ids' => $areaIds,
            ]);
            $application->update([
                'status' => ShahidarApplicationStatus::Onboarded,
                'user_id' => $user->id,
                'handled_by' => $admin->id,
                'handled_at' => now(),
            ]);

            return $user;
        });
    }
}
