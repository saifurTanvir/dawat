<?php

namespace App\Filament\Resources\CallRequests;

use App\Enums\CallRequestStatus;
use App\Filament\Actions\CallRequestActions;
use App\Filament\Resources\CallRequests\Pages\ManageCallRequests;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\Links;
use App\Filament\Support\PhoneField;
use App\Models\CallRequest;
use App\Models\User;
use App\Services\Admin\CallOnboarding;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            // F88: only the people still to contact; finished ones show only when asked for.
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                $filters = $livewire->tableFilters ?? [];
                $askedForStatus = filled($filters['status']['value'] ?? null);
                $notOnboarded = (bool) ($filters['not_onboarded']['isActive'] ?? false);
                $closedBy = filled($filters['done_by']['value'] ?? null);

                return $query->with('user.kitchen')
                    ->when(! $askedForStatus && ! $notOnboarded && ! $closedBy, fn (Builder $q) => $q->open())
                    ->orderByRaw("status = 'done'");
            })
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('name')->label('কে')->weight('semibold')
                    ->state(fn (CallRequest $r) => $r->name ?: ($r->user?->name ?: $r->phoneLabel()))
                    ->description(fn (CallRequest $r) => $r->phoneLabel().' · '.(CallRequest::SOURCES[$r->source] ?? $r->source))
                    ->url(fn (CallRequest $r) => $r->user ? Links::person($r->user) : null)
                    ->searchable(['name', 'phone']),
                TextColumn::make('tag')->label('ট্যাগ')->badge()->placeholder('—')
                    ->color(fn (?string $state) => match ($state) {
                        CallRequest::TAG_BADDA_CUSTOMER, CallRequest::TAG_TALK_BADDA => 'success',
                        CallRequest::TAG_TALK => 'info',
                        default => 'gray',
                    }),
                CallRequestActions::reasonColumn(),
                CallRequestActions::talksColumn(),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('created_at')->label('কবে')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(CallRequestStatus::options())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
                // F106 (owner, 23 Nov): Badda by default; the rest under "বাড্ডার বাইরে".
                SelectFilter::make('in_badda')->label('এলাকা')
                    ->options(['badda' => 'বাড্ডা', 'outside' => 'বাড্ডার বাইরে'])->default('badda')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'badda' => $query->where('in_badda', true),
                        'outside' => $query->where('in_badda', false),
                        default => $query,
                    }),
                // F106: Badda customers apart from the rest.
                SelectFilter::make('tag')->label('ট্যাগ')
                    ->options(collect([CallRequest::TAG_BADDA_CUSTOMER, CallRequest::TAG_CUSTOMER, CallRequest::TAG_TALK_BADDA, CallRequest::TAG_TALK])->mapWithKeys(fn ($t) => [$t => $t])->all()),
                // F88: who closed how many — pick a person to see the ones they finished.
                SelectFilter::make('done_by')->label('কে বন্ধ করেছেন')
                    ->options(fn () => User::query()
                        ->whereIn('id', CallRequest::query()->whereNotNull('done_by')->select('done_by'))
                        ->withCount(['closedCallRequests'])->orderByDesc('closed_call_requests_count')->get()
                        ->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->closed_call_requests_count.')'])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('done_by', $data['value'])->where('status', CallRequestStatus::Done)
                        : $query),
                // F70
                Filter::make('not_onboarded')->label('কথা হয়েছে, সিস্টেমে যোগ বাকি')
                    ->query(fn (Builder $query) => $query->whereIn('id', CallOnboarding::pending()->select('id'))),
            ])
            ->recordActions([...CallRequestActions::row(), CallRequestActions::onboard()->visible(fn (CallRequest $r) => CallOnboarding::pending()->whereKey($r->id)->exists())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCallRequests::route('/')];
    }
}
