<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Actions\ActForCookActions;
use App\Filament\Actions\PostModerationActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActForCookActions::plates(),
            ActForCookActions::edit(),
            ActForCookActions::soldOut(),
            ...PostModerationActions::make(),
            RemovalActions::deletePost(PostResource::getUrl('index')),
        ];
    }
}
