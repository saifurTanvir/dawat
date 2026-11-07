<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use App\Filament\Actions\UserBlockActions;
use App\Support\Roles;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('phone')->label('ফোন')->searchable()->copyable(),
                TextColumn::make('name')->label('নাম')->searchable()->placeholder('—'),
                TextColumn::make('roles.name')->label('রোল')->badge()->color('gray'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('created_at')->label('যোগ দিয়েছেন')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(UserStatus::class),
                SelectFilter::make('roles')->label('রোল')->relationship('roles', 'name')
                    ->options(array_combine(Roles::all(), Roles::all())),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(UserBlockActions::make()),
            ]);
    }
}
