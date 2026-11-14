<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use App\Models\Post;
use App\Support\Geo\Geocoder;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKitchen extends EditRecord
{
    protected static string $resource = KitchenResource::class;

    /** F62: the location box holds text; store it as the home point. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('where', $data)) {
            $point = app(Geocoder::class)->point($data['where']);
            if ($point) {
                $data['location'] = $point;
            }
            unset($data['where']);
        }

        return $data;
    }

    /** F35: a new reach also applies to the kitchen's posts that are live now. */
    protected function afterSave(): void
    {
        $kitchen = $this->record->refresh();
        $areaIds = $kitchen->reachAreas()->pluck('areas.id')->all();
        $kitchen->posts()->visibleNow()->get()->each(fn (Post $post) => $post->setReach($kitchen->reach_m, $areaIds));
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
