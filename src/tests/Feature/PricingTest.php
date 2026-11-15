<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\Tier\PlanHighlights;
use App\Services\Tier\PlanLimits;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function plan(string $slug): Plan
    {
        return Plan::query()->where('slug', $slug)->firstOrFail();
    }

    public function test_pricing_lists_free_and_pro_with_generated_features(): void
    {
        $response = $this->get('/pricing')->assertOk();

        $this->assertSame(['free', 'pro'], $response->viewData('plans')->pluck('slug')->all());
        $this->assertNull(Plan::query()->where('slug', 'pro_plus')->first());

        $html = $response->assertSeeInOrder(['Free', 'File hingga 2 MB', '25 proses file per hari', 'Pro', 'File hingga 100 MB', 'Bebas iklan', 'Unduh format SVG'])
            ->assertSee('Rp29.000')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'aria-label="Fitur '));
    }

    public function test_highlights_follow_the_plan_limits(): void
    {
        $lines = app(PlanHighlights::class)->for([
            'max_file_mb' => 50, 'max_files_per_op' => 10, 'file_ops_per_day' => null, 'links_per_day' => 0,
            'retention_minutes' => 120, 'custom_alias_max' => null, 'link_custom_expiry' => true, 'queue' => 'pro',
            'ads' => false, 'tool_qr_code_custom_color' => true,
        ]);

        $this->assertSame([
            'File hingga 50 MB',
            '10 file per proses',
            'Proses file tanpa batas harian',
            'Hasil disimpan 2 jam',
            'Alias link kustom tanpa batas',
            'Atur kedaluwarsa link',
            'Antrean proses prioritas',
            'Bebas iklan',
            'Pemilih warna kustom',
        ], $lines);
    }

    public function test_pricing_reflects_plan_changes_and_escapes_names(): void
    {
        $pro = $this->plan('pro');
        $pro->forceFill(['name' => '<script>alert(1)</script>', 'price_monthly' => 45000, 'limits' => [...$pro->limits, 'max_file_mb' => 64]])->save();
        app(PlanLimits::class)->flush();

        $this->get('/pricing')->assertOk()
            ->assertSee('Rp45.000')
            ->assertSee('File hingga 64 MB')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_reseeding_keeps_admin_edited_plans(): void
    {
        $pro = $this->plan('pro');
        $pro->forceFill(['name' => 'Pro Kreator', 'price_monthly' => 35000, 'limits' => [...$pro->limits, 'max_file_mb' => 64]])->save();

        $this->seed(PlanSeeder::class);

        $pro = $this->plan('pro');
        $this->assertSame(['Pro Kreator', 35000, 64], [$pro->name, $pro->price_monthly, $pro->limits['max_file_mb']]);
        $this->assertSame(2, Plan::count());
    }
}
