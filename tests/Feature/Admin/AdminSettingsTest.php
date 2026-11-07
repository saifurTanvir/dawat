<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Filament\Resources\QrCodes\Pages\ManageQrCodes;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Area;
use App\Models\QrCode;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_settings_pages_render(): void
    {
        QrCode::factory()->create();
        $this->actingAs($this->admin);

        foreach (['areas', 'tags', 'qr-codes'] as $page) {
            $this->get("/admin/{$page}")->assertOk();
        }
    }

    public function test_creating_an_area_from_lat_lng(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ManageAreas::class)
            ->callAction(CreateAction::class, [
                'name' => 'শ্যামলী',
                'slug' => 'Shyamoli',
                'center_lat' => 23.7746,
                'center_lng' => 90.3657,
                'radius_m' => 1500,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $area = Area::where('slug', 'shyamoli')->firstOrFail();
        $this->assertEqualsWithDelta(23.7746, $area->center->lat, 1e-6);
    }

    public function test_creating_a_qr_code_generates_a_code_and_poster(): void
    {
        $area = Area::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test(ManageQrCodes::class)
            ->callAction(CreateAction::class, ['area_id' => $area->id, 'label' => 'বায়তুল আমান মসজিদ', 'is_active' => true])
            ->assertHasNoActionErrors();

        $qr = QrCode::firstOrFail();
        $this->assertMatchesRegularExpression('/^[a-z2-9xyzk]{8}$/', $qr->code);

        $this->withoutVite();
        $this->get(route('filament.admin.qr.poster', $qr))->assertOk()->assertSee('<svg', false)->assertSee($qr->url());

        $png = $this->get(route('filament.admin.qr.png', $qr));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
    }

    public function test_qr_material_needs_an_admin(): void
    {
        $qr = QrCode::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('filament.admin.qr.png', $qr))->assertForbidden();
    }

    public function test_tags_page_lists_seeded_tags(): void
    {
        $this->seed(TagSeeder::class);
        $this->actingAs($this->admin);

        Livewire::test(ManageTags::class)->assertCountTableRecords(13);
    }
}
