<?php

namespace Tests\Feature\Import;

use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Filament\Pages\ImportPeople;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OtpCode;
use App\Models\PeopleImport;
use App\Models\User;
use App\Services\Import\PeopleImporter;
use App\Services\Import\PeopleTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** F59: customers, cooks and shahidars from an Excel/CSV list. */
class PeopleImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Area $badda;

    private Area $rampura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->badda = Area::where('name', 'মধ্য বাড্ডা')->first() ?? Area::factory()->create(['name' => 'মধ্য বাড্ডা', 'slug' => 'test-moddho-badda', 'is_active' => true]);
        $this->rampura = Area::where('slug', 'rampura')->first() ?? Area::factory()->create(['name' => 'রামপুরা', 'slug' => 'rampura', 'is_active' => true]);
        Area::whereKey([$this->badda->id, $this->rampura->id])->update(['is_active' => true]);
    }

    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $f = fopen($path, 'w');
        fwrite($f, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($f, $r);
        }
        fclose($f);

        return $path;
    }

    private const HEAD = ['মোবাইল', 'নাম', 'ভূমিকা', 'এলাকা', 'ঠিকানা', 'রান্নাঘরের নাম', 'লোকেশন লিংক', 'বেলা', 'প্লেট', 'দিন', 'ক্যাম্পেইন', 'নোট'];

    public function test_a_mixed_list_is_previewed_then_added_without_any_sms(): void
    {
        $existing = User::factory()->create(['phone' => '01733000004', 'name' => 'পুরোনো নাম']);
        $path = $this->csv([
            self::HEAD,
            ['০১৭১১০০০০০১', 'জামান সাহেব', 'গ্রাহক', 'মধ্য বাড্ডা', 'বাসা ১২, রোড ৩', '', 'https://maps.google.com/?q=23.7801,90.4253', 'দুপুর, রাত', '২', 'রবি, সোম', 'ফেসবুক নভেম্বর', 'ঝাল কম'],
            ['1811000002', 'রহিমা বেগম', 'গৃহিণী', 'রামপুরা', 'বাসা ৫', '', '', '', '', '', 'ফেসবুক গৃহিণী', ''],
            ['01911000003', 'করিম মিয়া', 'shahidar', 'মধ্য বাড্ডা, rampura', '', '', '', '', '', '', '', ''],
            ['01733000004', 'নতুন নাম', 'গ্রাহক', 'মধ্য বাড্ডা', 'ফ্ল্যাট ২এ, রোড ৯', '', '', '', '', 'প্রতিদিন', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
            ['01711000001', 'আবার জামান', 'গ্রাহক', 'মধ্য বাড্ডা', 'বাসা ১২', '', '', '', '', '', '', ''],
            ['0123', 'ভুল নম্বর', 'গ্রাহক', 'মধ্য বাড্ডা', 'বাসা', '', '', '', '', '', '', ''],
            ['01611000007', 'কেউ', 'গ্রাহক', 'চাঁদের দেশ', '', '', '', 'মাঝরাত', '', '', '', ''],
        ]);

        $import = app(PeopleImporter::class)->preview($path, 'list.csv', $this->admin);
        $plans = $import->rows()->pluck('plan', 'line')->all();
        $this->assertSame([2 => 'new', 3 => 'new', 4 => 'new', 5 => 'add_role', 7 => 'error', 8 => 'error', 9 => 'error'], $plans);
        $this->assertStringContainsString('লাইন 2', $import->rows()->where('line', 7)->value('message'));
        $this->assertStringContainsString('এলাকা মেলেনি', $import->rows()->where('line', 9)->value('message'));
        $this->assertStringContainsString('বেলা বোঝা যায়নি', $import->rows()->where('line', 9)->value('message'));
        $this->assertSame(0, Customer::count()); // preview adds nobody

        app(PeopleImporter::class)->run($import, $this->admin);
        $import->refresh();
        $this->assertSame([3, 1, 0, 3], [$import->added, $import->role_added, $import->skipped, $import->failed]);

        $c = Customer::whereHas('user', fn ($q) => $q->where('phone', '01711000001'))->sole();
        $this->assertSame(['lunch', 'dinner'], $c->meal_slots);
        $this->assertSame([0, 1], $c->weekdays);
        $this->assertSame(2, $c->plates);
        $this->assertSame('import', $c->source);
        $this->assertSame(CustomerStatus::Waiting, $c->status);
        $this->assertEqualsWithDelta(23.7801, $c->location->lat, 0.0001);
        $this->assertSame('ফেসবুক নভেম্বর', $c->user->campaign);
        $this->assertSame('ঝাল কম', $c->note);

        $kitchen = Kitchen::sole();
        $this->assertSame('01811000002', $kitchen->user->phone); // Excel dropped the leading 0
        $this->assertSame('রহিমা বেগম-এর রান্নাঘর', $kitchen->display_name);
        $this->assertSame(KitchenStatus::Pending, $kitchen->status);
        $this->assertSame($this->rampura->id, $kitchen->area_id);

        $shahidar = User::where('phone', '01911000003')->sole();
        $this->assertTrue($shahidar->isShahidar());
        $this->assertEqualsCanonicalizing([$this->badda->id, $this->rampura->id], $shahidar->shahidarAreas()->pluck('areas.id')->all());

        $this->assertSame('পুরোনো নাম', $existing->fresh()->name); // existing details untouched
        $this->assertNotNull($existing->fresh()->customer);
        $this->assertSame(0, OtpCode::count());
    }

    public function test_already_there_is_skipped_and_admins_are_refused(): void
    {
        $cook = Kitchen::factory()->create();
        $path = $this->csv([
            ['phone', 'name', 'role', 'area', 'address'],
            [$cook->user->phone, 'নাম', 'cook', 'rampura', ''],
            [$this->admin->phone ?? '01700000099', 'অ্যাডমিন', 'customer', 'rampura', 'বাসা ১, রোড ১'],
        ]);
        if (! $this->admin->phone) {
            $this->admin->forceFill(['phone' => '01700000099'])->save();
        }

        $import = app(PeopleImporter::class)->preview($path, 'en.csv', $this->admin);
        $this->assertSame(['exists', 'error'], $import->rows()->pluck('plan')->all());
        app(PeopleImporter::class)->run($import, $this->admin);
        $this->assertSame(1, Kitchen::count());
        $this->assertNull($this->admin->fresh()->customer);
    }

    public function test_xlsx_template_round_trip_and_admin_page(): void
    {
        $this->actingAs($this->admin);

        // The template the admin downloads is itself a valid list (its three examples).
        $template = app(PeopleTemplate::class)->template();
        $import = app(PeopleImporter::class)->preview($template, 'template.xlsx', $this->admin);
        $this->assertSame(['new', 'new', 'new'], $import->rows()->pluck('plan')->all());

        // A hand-made sheet with an extra first sheet and numbers stored as numbers.
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('নোট');
        $w->addRow(Row::fromValues(['এই শিটে কিছু নেই']));
        $w->addNewSheetAndMakeItCurrent()->setName('তালিকা');
        $w->addRow(Row::fromValues(['মোবাইল (আবশ্যক)', 'নাম', 'ভূমিকা', 'এলাকা']));
        $w->addRow(Row::fromValues([1912345678, 'সালমা', 'শাহিদার', 'রামপুরা']));
        $w->close();
        $file = UploadedFile::fake()->createWithContent('marketing.xlsx', file_get_contents($path));

        Livewire::test(ImportPeople::class)
            ->callAction('upload', ['file' => $file])
            ->assertHasNoActionErrors()
            ->assertSee('সালমা')
            ->assertSee('নতুন যোগ হবে')
            ->callAction('run')
            ->assertNotified();

        $this->assertTrue(User::where('phone', '01912345678')->sole()->isShahidar());
        $this->assertSame('done', PeopleImport::latest('id')->value('status'));
        $this->get('/admin/import-people')->assertOk()->assertSee('marketing.xlsx');
        $this->get('/admin')->assertSee('তালিকা থেকে যোগ করুন');
    }

    public function test_a_file_that_cannot_be_read_says_why(): void
    {
        $this->actingAs($this->admin);
        $file = UploadedFile::fake()->createWithContent('list.csv', "নাম,এলাকা\nজামান,রামপুরা\n");

        Livewire::test(ImportPeople::class)->callAction('upload', ['file' => $file])->assertNotified('ফাইলটা পড়া গেল না');
        $this->assertSame(0, PeopleImport::count());
    }
}
