<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use App\Models\Post;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKitchen extends EditRecord
{
    protected static string $resource = KitchenResource::class;

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
