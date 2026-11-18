<?php

namespace App\Filament\Resources\StaffRoles;

use App\Filament\Resources\StaffRoles\Pages\ManageStaffRoles;
use App\Filament\Support\FeatureGate;
use App\Support\AdminFeatures;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * F69: staff roles an admin makes — a name and the parts of the panel it
 * opens, e.g. "কল টিম" = যোগাযোগ ও ফলো-আপ. Admins only.
 */
class StaffRoleResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = Role::class;

    protected static ?string $slug = 'staff-roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $modelLabel = 'স্টাফের ভূমিকা';

    protected static ?string $pluralModelLabel = 'স্টাফের ভূমিকা';

    protected static ?int $navigationSort = 91;

    public static function getEloquentQuery(): Builder
    {
        return Role::query()->whereNotIn('name', Roles::all())->where('guard_name', 'web');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('ভূমিকার নাম')->required()->maxLength(60)
                ->placeholder('যেমন: কল টিম')
                ->notIn(Roles::all())
                ->unique('roles', 'name', ignoreRecord: true)
                ->validationMessages(['unique' => 'এই নামে আগেই একটা ভূমিকা আছে।', 'not_in' => 'এই নামটা দাওয়াতের নিজের, অন্য নাম দিন।']),
            CheckboxList::make('features')->label('কোন কাজগুলো দেখবেন')->required()
                ->options(AdminFeatures::options())->descriptions(AdminFeatures::descriptions())
                ->afterStateHydrated(fn ($component, ?Role $record) => $component->state(self::featuresOf($record)))
                ->helperText('এর বাইরে কিছু দেখবেন না। এলাকা, স্টাফ, লগইন লিংক, "তাঁর মতো করে দেখুন" আর মুছে ফেলা শুধু অ্যাডমিনের।')
                ->columns(1),
        ]);
    }

    /** @return list<string> */
    public static function featuresOf(?Role $role): array
    {
        return $role ? $role->permissions->pluck('name')
            ->filter(fn ($n) => str_starts_with($n, AdminFeatures::PREFIX))
            ->map(fn ($n) => substr($n, strlen(AdminFeatures::PREFIX)))
            ->values()->all() : [];
    }

    public static function save(?Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role ??= new Role(['guard_name' => 'web']);
            $role->name = trim($data['name']);
            $role->save();
            $keys = array_values(array_intersect(array_keys(AdminFeatures::FEATURES), $data['features'] ?? []));
            $role->syncPermissions(array_map(fn ($k) => Permission::findOrCreate(AdminFeatures::PREFIX.$k, 'web'), $keys));

            return $role;
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('permissions')->withCount('users'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('ভূমিকা')->weight('semibold')->searchable(),
                TextColumn::make('features')->label('যা দেখবেন')->wrap()
                    ->state(fn (Role $record) => collect(self::featuresOf($record))->map(fn ($k) => AdminFeatures::FEATURES[$k][0] ?? $k)->implode(', ')),
                TextColumn::make('users_count')->label('স্টাফ'),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Role $record, array $data) => self::save($record, $data)),
                DeleteAction::make()->modalDescription('এই ভূমিকার স্টাফরা আর অ্যাডমিন প্যানেলে ঢুকতে পারবেন না।'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaffRoles::route('/')];
    }
}
