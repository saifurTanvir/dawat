<?php

namespace App\Filament\Resources\Staff;

use App\Enums\UserStatus;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\StaffRoles\StaffRoleResource;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\PhoneField;
use App\Models\User;
use App\Services\Admin\StaffView;
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
        // F71: admins too — a staff member can be made a full admin (the admin's clone).
        return User::query()->whereHas('roles', fn ($q) => $q->whereNotIn('name', Roles::all())->orWhere('name', Roles::ADMIN));
    }

    /** F71: "admin" = the admin's clone — sees and does everything an admin does. */
    public const FULL_ADMIN = 'admin';

    /** @return array<int|string, string> */
    public static function roleOptions(): array
    {
        return [self::FULL_ADMIN => 'অ্যাডমিন — সব কিছু (অ্যাডমিনের মতো)']
            + StaffRoleResource::getEloquentQuery()->orderBy('name')->pluck('name', 'id')->all();
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
                ->afterStateHydrated(fn ($component, ?User $record) => $component->state($record?->isAdmin() ? self::FULL_ADMIN : self::staffRole($record)?->id))
                ->disabled(fn (?User $record) => $record?->id === auth()->id())
                ->dehydrated(fn (?User $record) => $record?->id !== auth()->id())
                ->helperText('"অ্যাডমিন — সব কিছু" দিলে ঠিক অ্যাডমিনের মতো সব দেখবেন ও করবেন, স্টাফ যোগ করাসহ। অন্যগুলো "স্টাফের ভূমিকা" পাতায় বানান। নিজের ভূমিকা নিজে বদলানো যায় না।'),
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
                    throw ValidationException::withMessages(['phone' => 'এই নম্বর আগে থেকেই একজন অ্যাডমিনের।']);
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

            if (! array_key_exists('staff_role', $data)) {
                return $user->fresh('roles'); // their own role stays
            }
            $full = (string) $data['staff_role'] === self::FULL_ADMIN;
            $role = $full ? null : Role::findById((int) $data['staff_role'], 'web');
            // One staff role at a time; admin is the whole panel.
            foreach ($user->roles->filter(fn ($r) => ! in_array($r->name, Roles::all(), true) && $r->id !== $role?->id) as $r) {
                $user->removeRole($r);
            }
            if ($full) {
                $user->assignRole(Roles::ADMIN);
            } else {
                if ($user->hasRole(Roles::ADMIN)) {
                    self::keepAnAdmin($user);
                    $user->removeRole(Roles::ADMIN);
                }
                $user->assignRole($role);
            }

            return $user->fresh('roles');
        });
    }

    /** Never leave Dawat without an admin. */
    private static function keepAnAdmin(User $leaving): void
    {
        if (! User::role(Roles::ADMIN)->whereKeyNot($leaving->id)->exists()) {
            throw ValidationException::withMessages(['staff_role' => 'অন্তত একজন অ্যাডমিন থাকতে হবে।']);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles.permissions'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('নাম')->weight('semibold')->searchable()->description(fn (User $u) => $u->phone),
                TextColumn::make('email')->label('ইমেইল')->searchable(),
                TextColumn::make('staff_role')->label('ভূমিকা')->badge()
                    ->state(fn (User $u) => $u->isAdmin() ? 'অ্যাডমিন' : self::staffRole($u)?->name)
                    ->color(fn (User $u) => $u->isAdmin() ? 'success' : 'gray'),
                TextColumn::make('features')->label('যা দেখবেন')->wrap()
                    ->state(fn (User $u) => $u->isAdmin() ? 'সব কিছু' : collect(StaffRoleResource::featuresOf(self::staffRole($u)))->map(fn ($k) => AdminFeatures::FEATURES[$k][0] ?? $k)->implode(', ')),
            ])
            ->recordActions([
                EditAction::make()->using(fn (User $record, array $data) => self::save($record, $data)),
                // F71: see the panel exactly as this staff member does.
                Action::make('viewAs')->label('তাঁর মতো করে দেখুন')->icon(Heroicon::OutlinedEye)->color('info')
                    ->visible(fn (User $record) => ! $record->isAdmin() && ! $record->isBlocked())
                    ->requiresConfirmation()
                    ->modalDescription('প্যানেল এই স্টাফের চোখে খুলবে। উপরের বার থেকে "অ্যাডমিনে ফিরুন"। এ সময় যা করবেন, তা এই স্টাফের নামে লেখা হবে।')
                    ->action(fn (User $record) => StaffView::start(auth()->user(), $record)),
                Action::make('revoke')->label('প্যানেল বন্ধ')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (User $record) => $record->id !== auth()->id())
                    ->requiresConfirmation()->modalDescription('এই মানুষটা আর অ্যাডমিন প্যানেলে ঢুকতে পারবেন না। অ্যাপের সাধারণ অ্যাকাউন্ট থাকবে।')
                    ->action(function (User $record) {
                        if ($record->hasRole(Roles::ADMIN)) {
                            self::keepAnAdmin($record);
                            $record->removeRole(Roles::ADMIN);
                        }
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
