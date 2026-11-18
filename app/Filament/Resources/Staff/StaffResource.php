<?php

namespace App\Filament\Resources\Staff;

use App\Enums\UserStatus;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\StaffRoles\StaffRoleResource;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\PhoneField;
use App\Models\User;
use App\Support\AdminFeatures;
use App\Support\PhoneNumber;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * F69: Dawat's staff — people who log in to the admin panel with an email
 * and password but see only what their role opens. Admins only.
 */
class StaffResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $modelLabel = 'স্টাফ';

    protected static ?string $pluralModelLabel = 'স্টাফ';

    protected static ?int $navigationSort = 90;

    public static function getEloquentQuery(): Builder
    {
        return User::query()->whereHas('roles', fn ($q) => $q->whereNotIn('name', Roles::all()));
    }

    /** @return array<int, string> */
    public static function roleOptions(): array
    {
        return StaffRoleResource::getEloquentQuery()->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(60),
            PhoneField::make()->disabledOn('edit')->dehydrated(fn (string $operation) => $operation === 'create'),
            TextInput::make('email')->label('ইমেইল (লগইনের জন্য)')->email()->required()->maxLength(120)
                ->unique('users', 'email', ignoreRecord: true)->validationMessages(['unique' => 'এই ইমেইল আগেই কারও আছে।']),
            TextInput::make('password')->label('পাসওয়ার্ড')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'বদলাতে চাইলে লিখুন, না হলে খালি রাখুন।' : 'কমপক্ষে ৮ অক্ষর। স্টাফকে নিজে জানিয়ে দিন।'),
            Select::make('staff_role')->label('ভূমিকা')->required()->options(fn () => self::roleOptions())
                ->afterStateHydrated(fn ($component, ?User $record) => $component->state(self::staffRole($record)?->id))
                ->helperText('আগে "স্টাফের ভূমিকা" পাতায় ভূমিকা বানান।'),
        ]);
    }

    public static function staffRole(?User $user): ?Role
    {
        return $user?->roles->first(fn ($r) => ! in_array($r->name, Roles::all(), true));
    }

    /** Creates (or upgrades an existing phone's account to) a staff member, or saves edits. */
    public static function save(?User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (! $user) {
                $phone = PhoneNumber::normalize((string) $data['phone']);
                $user = User::firstOrNew(['phone' => $phone]);
                if ($user->exists && $user->isAdmin()) {
                    throw ValidationException::withMessages(['phone' => 'এই নম্বর একজন অ্যাডমিনের।']);
                }
                if (! $user->exists) {
                    $user->status = UserStatus::Active;
                }
            }
            $user->name = trim($data['name']);
            $user->email = strtolower(trim($data['email']));
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $user->save();
            if (! $user->hasRole(Roles::BUYER)) {
                $user->assignRole(Roles::BUYER);
            }

            $role = Role::findById((int) $data['staff_role'], 'web');
            // One staff role at a time.
            $old = $user->roles->filter(fn ($r) => ! in_array($r->name, Roles::all(), true) && $r->id !== $role->id);
            foreach ($old as $r) {
                $user->removeRole($r);
            }
            $user->assignRole($role);

            return $user->fresh('roles');
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles.permissions'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('নাম')->weight('semibold')->searchable()->description(fn (User $u) => $u->phone),
                TextColumn::make('email')->label('ইমেইল')->searchable(),
                TextColumn::make('staff_role')->label('ভূমিকা')->badge()->state(fn (User $u) => self::staffRole($u)?->name),
                TextColumn::make('features')->label('যা দেখবেন')->wrap()
                    ->state(fn (User $u) => collect(StaffRoleResource::featuresOf(self::staffRole($u)))->map(fn ($k) => AdminFeatures::FEATURES[$k][0] ?? $k)->implode(', ')),
            ])
            ->recordActions([
                EditAction::make()->using(fn (User $record, array $data) => self::save($record, $data)),
                Action::make('revoke')->label('প্যানেল বন্ধ')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->requiresConfirmation()->modalDescription('এই মানুষটা আর অ্যাডমিন প্যানেলে ঢুকতে পারবেন না। অ্যাপের সাধারণ অ্যাকাউন্ট থাকবে।')
                    ->action(function (User $record) {
                        foreach ($record->roles->filter(fn ($r) => ! in_array($r->name, Roles::all(), true)) as $r) {
                            $record->removeRole($r);
                        }
                        $record->forceFill(['password' => null])->save();
                        Notification::make()->title('প্যানেল বন্ধ করা হয়েছে')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
