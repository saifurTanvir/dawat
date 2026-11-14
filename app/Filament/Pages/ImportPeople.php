<?php

namespace App\Filament\Pages;

use App\Models\PeopleImport;
use App\Models\PeopleImportRow;
use App\Services\Import\PeopleImporter;
use App\Services\Import\PeopleTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * F59: add customers, cooks and shahidars from a marketing list. Upload →
 * see what will happen to every line → "যোগ করুন" → result file.
 */
class ImportPeople extends Page
{
    protected string $view = 'filament.pages.import-people';

    protected static ?string $slug = 'import-people';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $navigationLabel = 'তালিকা থেকে যোগ';

    protected static ?string $title = 'তালিকা থেকে যোগ করুন';

    protected static ?int $navigationSort = 5;

    /** The list being looked at. */
    #[Url(as: 'import')]
    public ?int $importId = null;

    public function getImport(): ?PeopleImport
    {
        return $this->importId ? PeopleImport::with('uploader')->find($this->importId) : null;
    }

    protected function getHeaderActions(): array
    {
        return [$this->uploadAction(), $this->templateAction()];
    }

    public function templateAction(): Action
    {
        return Action::make('template')
            ->label('টেমপ্লেট নামান')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (): BinaryFileResponse => response()
                ->download(app(PeopleTemplate::class)->template(), 'dawat-talika-template.xlsx')
                ->deleteFileAfterSend());
    }

    public function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('ফাইল আপলোড করুন')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('এক্সেল বা CSV আপলোড')
            ->modalDescription('এখনই কেউ যোগ হবে না। আগে প্রতিটা লাইনে কী হবে দেখাবে।')
            ->modalSubmitActionLabel('দেখুন')
            ->schema([
                FileUpload::make('file')->label('ফাইল (.xlsx বা .csv)')
                    ->required()
                    ->disk('local')->directory('imports')->visibility('private')
                    ->storeFileNamesIn('file_name')
                    ->maxSize(5 * 1024)
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ]),
            ])
            ->action(function (array $data) {
                $stored = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $name = is_array($data['file_name'] ?? null) ? reset($data['file_name']) : ($data['file_name'] ?? basename($stored));
                try {
                    $import = app(PeopleImporter::class)->preview(Storage::disk('local')->path($stored), (string) $name, auth()->user());
                } catch (RuntimeException $e) {
                    Notification::make()->title('ফাইলটা পড়া গেল না')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($stored);
                }
                $this->importId = $import->id;
            });
    }

    public function runAction(): Action
    {
        return Action::make('run')
            ->label(fn () => 'যোগ করুন ('.$this->ready().' জন)')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn () => $this->getImport()?->status === 'preview' && $this->ready() > 0)
            ->requiresConfirmation()
            ->modalHeading('তালিকার সবাইকে যোগ করবেন?')
            ->modalDescription('ভুল থাকা আর আগে থেকে থাকা লাইন বাদ যাবে। কাউকে SMS যাবে না। গৃহিণীরা অনুমোদনের অপেক্ষায় থাকবেন।')
            ->action(function () {
                $import = app(PeopleImporter::class)->run($this->getImport(), auth()->user());
                Notification::make()->title('যোগ করা হয়েছে')
                    ->body("নতুন {$import->added} জন, ভূমিকা যোগ {$import->role_added} জন, বাদ {$import->skipped}, হয়নি {$import->failed}।")
                    ->success()->send();
            });
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('বাতিল')
            ->color('gray')
            ->visible(fn () => $this->getImport()?->status === 'preview')
            ->action(function () {
                $this->getImport()->forceFill(['status' => 'cancelled'])->save();
                $this->importId = null;
            });
    }

    public function resultsAction(): Action
    {
        return Action::make('results')
            ->label('ফলাফল নামান (CSV)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn () => $this->getImport() !== null)
            ->action(fn (): BinaryFileResponse => response()
                ->download(app(PeopleTemplate::class)->results($this->getImport()), 'dawat-talika-'.$this->importId.'-result.csv')
                ->deleteFileAfterSend());
    }

    /** Lines that will be added. */
    private function ready(): int
    {
        return $this->importId ? PeopleImportRow::where('people_import_id', $this->importId)->whereIn('plan', ['new', 'add_role'])->count() : 0;
    }

    public function open(int $id): void
    {
        $this->importId = $id;
    }

    protected function getViewData(): array
    {
        $import = $this->getImport();

        return [
            'import' => $import,
            'counts' => $import ? $import->rows()->reorder()->toBase()->selectRaw('plan, COUNT(*) AS n')->groupBy('plan')->pluck('n', 'plan') : collect(),
            // Errors first, so they are fixed; at most 500 lines on screen (the CSV has all).
            'rows' => $import ? $import->rows()->with('user')->reorder()->orderByRaw("FIELD(plan, 'error', 'add_role', 'exists', 'new')")->orderBy('line')->limit(500)->get() : collect(),
            'history' => PeopleImport::with('uploader')->whereIn('status', ['preview', 'done'])->latest('id')->limit(10)->get(),
            'roles' => PeopleImportRow::ROLES,
            'plans' => PeopleImportRow::PLANS,
            'results' => PeopleImportRow::RESULTS,
        ];
    }
}
