<?php

namespace App\Filament\RelationManagers;

class BuyerOrdersRelationManager extends OrdersRelationManager
{
    protected static string $relationship = 'boughtOrders';

    protected static ?string $title = 'ফিড থেকে অর্ডার';

    protected static array $hide = ['recipient'];
}
