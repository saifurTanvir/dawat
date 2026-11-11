<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\OrderHistoryTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** F49: a person's boxes; subclasses name the relationship and hide the owner's own column. */
abstract class OrdersRelationManager extends RelationManager
{
    protected static ?string $title = 'বক্স';

    /** @var list<string> */
    protected static array $hide = [];

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return OrderHistoryTable::configure($table, static::$hide);
    }
}
