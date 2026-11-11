<?php

namespace App\Filament\RelationManagers;

class KitchenOrdersRelationManager extends OrdersRelationManager
{
    protected static string $relationship = 'orders';

    protected static array $hide = ['kitchen'];
}
