<?php

namespace App\Filament\RelationManagers;

class ShahidarOrdersRelationManager extends OrdersRelationManager
{
    protected static string $relationship = 'shahidarOrders';

    protected static array $hide = ['shahidar'];
}
