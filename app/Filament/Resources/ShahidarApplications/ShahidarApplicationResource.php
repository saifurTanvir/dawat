<?php

namespace App\Filament\Resources\ShahidarApplications;

use App\Enums\ShahidarApplicationStatus as Status;
use App\Filament\Resources\ShahidarApplications\Pages\ListShahidarApplications;
use App\Filament\Resources\ShahidarApplications\Pages\ViewShahidarApplication;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Models\Area;
use App\Models\Order;
use App\Models\ShahidarApplication;
use App\Services\Shahidars\ShahidarApplications;
use App\Support\PhoneNumber;
use App\Support\ShahidarApplicationOptions as Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** F53: people who applied on the website to work as a shahidar. */
class ShahidarApplicationResource extends Resource
{
    protected static ?string $model = ShahidarApplication::class;

    protected static ?string $slug = 'shahidar-applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'শাহিদার আবেদন';

    protected static ?string $pluralModelLabel = 'শাহিদার আবেদন';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 31;

    public static function getNavigationBadge(): ?string
    {
        $new = ShahidarApplication::where('status', Status::New)->count();

        return $new ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return 'শাহিদার আবেদন: '.$record->name;
    }

    private static function yesNo(?bool $value): string
    {
        return $value === null ? '—' : ($value ? 'আছে' : 'নেই');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('areas'))
            ->recordUrl(fn (ShahidarApplication $record) => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')->label('কবে')->since()->sortable(),
                TextColumn::make('name')->label('নাম')->searchable()->weight('semibold')
                    ->description(fn (ShahidarApplication $record) => Options::label(Options::GENDER, $record->gender).($record->age ? ', '.$record->age.' বছর' : '')),
                TextColumn::make('phone')->label('ফোন')->searchable()->copyable(),
                TextColumn::make('areas.name')->label('কাজের এলাকা')->badge()->color('success'),
                TextColumn::make('vehicle')->label('যানবাহন')->formatStateUsing(fn ($state) => Options::label(Options::VEHICLE, $state)),
                TextColumn::make('job')->label('এখন কী করেন')->formatStateUsing(fn ($state) => Options::label(Options::JOB, $state))->toggleable(),
                TextColumn::make('hours')->label('সময়')->formatStateUsing(fn ($state) => Options::label(Options::HOURS, $state))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('অবস্থা')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(collect(Status::cases())->mapWithKeys(fn (Status $s) => [$s->value => $s->label()])),
                SelectFilter::make('area')->label('এলাকা')
                    ->options(fn () => Area::orderBy('sort_order')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('areas', fn (Builder $query) => $query->whereKey($data['value'])) : $query),
                SelectFilter::make('vehicle')->label('যানবাহন')->options(Options::VEHICLE),
                SelectFilter::make('gender')->label('লিঙ্গ')->options(Options::GENDER),
            ])
            ->recordActions([
                self::whatsappAction(),
                self::contactedAction(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $opt = fn (array $options) => fn ($state) => Options::label($options, $state);

        return $schema->components([
            Section::make('আবেদনকারী')->columns(3)->schema([
                TextEntry::make('name')->label('নাম'),
                TextEntry::make('phone')->label('ফোন')->copyable(),
                TextEntry::make('status')->label('অবস্থা')->badge(),
                TextEntry::make('gender')->label('লিঙ্গ')->formatStateUsing($opt(Options::GENDER)),
                TextEntry::make('age')->label('বয়স')->placeholder('—'),
                TextEntry::make('lives_in')->label('কোথায় থাকেন'),
                TextEntry::make('areas.name')->label('কাজ করতে চান')->badge()->color('success')->columnSpan(3),
            ]),
            Section::make('কাজ আর যানবাহন')->columns(3)->schema([
                TextEntry::make('job')->label('এখন কী করেন')->formatStateUsing($opt(Options::JOB)),
                TextEntry::make('job_note')->label('কাজের বিবরণ')->placeholder('—'),
                TextEntry::make('experience')->label('ডেলিভারির অভিজ্ঞতা')->placeholder('—'),
                TextEntry::make('vehicle')->label('যানবাহন')->formatStateUsing($opt(Options::VEHICLE)),
                TextEntry::make('has_license')->label('ড্রাইভিং লাইসেন্স')->state(fn (ShahidarApplication $record) => self::yesNo($record->has_license)),
                TextEntry::make('has_smartphone')->label('স্মার্টফোন')->state(fn (ShahidarApplication $record) => self::yesNo($record->has_smartphone)),
                TextEntry::make('has_nid')->label('জাতীয় পরিচয়পত্র')->state(fn (ShahidarApplication $record) => self::yesNo($record->has_nid)),
            ]),
            Section::make('সময়')->columns(3)->schema([
                TextEntry::make('hours')->label('কতক্ষণ')->formatStateUsing($opt(Options::HOURS)),
                TextEntry::make('slots')->label('কোন সময়ে')->state(fn (ShahidarApplication $record) => Options::labels(Options::SLOTS, $record->slots)),
                TextEntry::make('days_per_week')->label('সপ্তাহে কত দিন')->formatStateUsing(fn (int $state) => $state === 7 ? 'প্রতিদিন' : Order::banglaDigits((string) $state).' দিন'),
            ]),
            Section::make('অন্যান্য')->columns(3)->schema([
                TextEntry::make('heard_from')->label('কোথা থেকে জেনেছেন')->formatStateUsing($opt(Options::HEARD_FROM))->placeholder('—'),
                TextEntry::make('note')->label('আবেদনকারীর কথা')->placeholder('—')->columnSpan(2),
                TextEntry::make('created_at')->label('আবেদনের সময়')->dateTime('j M Y, g:i a', config('app.display_timezone')),
                TextEntry::make('admin_note')->label('অ্যাডমিনের নোট')->placeholder('—'),
                TextEntry::make('handledBy.name')->label('কে দেখেছেন')->placeholder('—'),
                TextEntry::make('user.name')->label('শাহিদার অ্যাকাউন্ট')->placeholder('—')
                    ->url(fn (ShahidarApplication $record) => $record->user_id ? ShahidarResource::getUrl('view', ['record' => $record->user_id]) : null),
            ]),
        ]);
    }

    public static function callAction(): Action
    {
        return Action::make('call')
            ->label('কল করুন')
            ->icon(Heroicon::OutlinedPhone)
            ->color('gray')
            ->url(fn (ShahidarApplication $record) => 'tel:+'.PhoneNumber::international($record->phone));
    }

    public static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label('হোয়াটসঅ্যাপ')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->url(fn (ShahidarApplication $record) => 'https://wa.me/'.PhoneNumber::international($record->phone).'?text='.rawurlencode(
                "আসসালামু আলাইকুম {$record->name}, দাওয়াত থেকে বলছি। আপনি শাহিদার হিসেবে কাজ করতে আবেদন করেছেন — কথা বলার জন্য কখন সময় হবে?"
            ), shouldOpenInNewTab: true);
    }

    public static function contactedAction(): Action
    {
        return Action::make('contacted')
            ->label('কথা হয়েছে')
            ->icon(Heroicon::OutlinedCheck)
            ->color('info')
            ->visible(fn (ShahidarApplication $record) => $record->status === Status::New)
            ->schema([Textarea::make('note')->label('কী কথা হলো (ঐচ্ছিক)')->maxLength(500)])
            ->action(function (ShahidarApplication $record, array $data) {
                app(ShahidarApplications::class)->mark($record, Status::Contacted, auth()->user(), $data['note'] ?: null);
                Notification::make()->title('কথা হয়েছে বলে রাখা হলো')->success()->send();
            });
    }

    public static function onboardAction(): Action
    {
        return Action::make('onboard')
            ->label('শাহিদার বানান')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->visible(fn (ShahidarApplication $record) => $record->status->isOpen())
            ->modalDescription('এই নম্বরে শাহিদার অ্যাকাউন্ট খুলবে (আগে অ্যাকাউন্ট থাকলে সেটাতেই রোল যোগ হবে)। OTP দিয়ে লগইন করবেন।')
            ->fillForm(fn (ShahidarApplication $record) => ['name' => $record->name, 'area_ids' => $record->areas->pluck('id')->all()])
            ->schema([
                TextInput::make('name')->label('নাম')->required()->maxLength(60),
                Select::make('area_ids')->label('কোন এলাকায় কাজ করবেন')
                    ->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))
                    ->multiple()->required(),
            ])
            ->action(function (ShahidarApplication $record, array $data, $livewire) {
                $user = app(ShahidarApplications::class)->onboard($record, auth()->user(), $data['area_ids'], $data['name']);
                Notification::make()->title("{$user->name} এখন শাহিদার")->success()->send();
                $livewire->redirect(ShahidarResource::getUrl('view', ['record' => $user]));
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('বাদ দিন')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (ShahidarApplication $record) => $record->status->isOpen())
            ->schema([Textarea::make('note')->label('কেন (শুধু অ্যাডমিনরা দেখবেন)')->maxLength(500)])
            ->action(function (ShahidarApplication $record, array $data) {
                app(ShahidarApplications::class)->mark($record, Status::Rejected, auth()->user(), $data['note'] ?: null);
                Notification::make()->title('আবেদন বাদ দেওয়া হলো')->send();
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('আবার খুলুন')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (ShahidarApplication $record) => $record->status === Status::Rejected)
            ->requiresConfirmation()
            ->action(fn (ShahidarApplication $record) => app(ShahidarApplications::class)->mark($record, Status::Contacted, auth()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShahidarApplications::route('/'),
            'view' => ViewShahidarApplication::route('/{record}'),
        ];
    }
}
