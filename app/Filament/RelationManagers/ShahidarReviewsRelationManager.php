<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Kitchens\RelationManagers\ReviewsRelationManager;

class ShahidarReviewsRelationManager extends ReviewsRelationManager
{
    protected static string $relationship = 'deliveredReviews';

    protected static ?string $title = 'রিভিউ';
}
