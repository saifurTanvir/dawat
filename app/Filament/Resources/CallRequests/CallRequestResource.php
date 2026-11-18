<?php

namespace App\Filament\Resources\CallRequests;

use App\Enums\CallRequestStatus;
use App\Filament\Actions\CallRequestActions;
use App\Filament\Resources\CallRequests\Pages\ManageCallRequests;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\Links;
use App\Filament\Support\PhoneField;
use App\Models\CallRequest;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * F66: "যোগাযোগ করতে হবে" — people who want to talk to the owner before
 * joining. One tap to call on mobile or open WhatsApp; notes and status.
 */
class CallRequestResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = CallRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowUpRight;

    protected static string|UnitEnum|null $navigationGroup = 'রাঁধুনি ও খাবার';

    protected static ?string $navigationLabel = 'যোগাযোগ করতে হবে';

    protected static ?string $modelLabel = 'যোগাযোগ';

    protected static ?string $pluralModelLabel = 'যোগাযোগ করতে হবে';

    protected static ?string $slug = 'call-list';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $open = CallRequest::open()->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            PhoneField::make(),
            TextInput::make('name')->label('নাম')->maxLength(100),
            Textarea::make('reason')->label('কেন কথা বলতে চান')->required()->maxLength(1000)->rows(3)
                ->placeholder('যেমন: খাবার দেওয়ার আগে কয়েকটা প্রশ্ন আছে')->columnSpanFull(),
            Select::make('source')->label('কোথা থেকে')->options(CallRequest::SOURCES)->default('whatsapp')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Open ones first, oldest first; done ones at the bottom.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user.kitchen')->orderByRaw("status = 'done'"))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('name')->label('কে')->weight('semibold')
                    ->state(fn (CallRequest $r) => $r->name ?: ($r->user?->name ?: $r->phone))
                    ->description(fn (CallRequest $r) => $r->phone.' · '.(CallRequest::SOURCES[$r->source] ?? $r->source))
                    ->url(fn (CallRequest $r) => $r->user ? Links::person($r->user) : null)
                    ->searchable(['name', 'phone']),
                CallRequestActions::reasonColumn(),
                TextColumn::make('latest_note')->label('শেষ রিমার্কস')->placeholder('—')->wrap()
                    ->state(fn (CallRequest $r) => $r->latestNote())
                    ->formatStateUsing(fn (?string $state) => $state ? Str::limit($state, 50) : null),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('created_at')->label('কবে')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(CallRequestStatus::options())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
            ])
            ->recordActions([...CallRequestActions::row(), CallRequestActions::done()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCallRequests::route('/')];
    }
}
