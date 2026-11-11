<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Kitchens\RelationManagers\ReviewsRelationManager;

class CustomerReviewsRelationManager extends ReviewsRelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'দেওয়া রিভিউ';
}
