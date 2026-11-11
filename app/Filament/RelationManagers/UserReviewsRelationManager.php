<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Kitchens\RelationManagers\ReviewsRelationManager;

class UserReviewsRelationManager extends ReviewsRelationManager
{
    protected static string $relationship = 'givenReviews';

    protected static ?string $title = 'দেওয়া রিভিউ';
}
