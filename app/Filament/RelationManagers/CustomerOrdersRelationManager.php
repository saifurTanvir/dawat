<?php

namespace App\Filament\RelationManagers;

class CustomerOrdersRelationManager extends OrdersRelationManager
{
    protected static string $relationship = 'orders';

    protected static array $hide = ['recipient'];
}
