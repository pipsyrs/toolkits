<?php

namespace Tests\Feature;

use App\Enums\FileOperationStatus;
use App\Jobs\ProcessFileOperation;
use App\Livewire\Client;
use App\Livewire\Tools;
use App\Models\FileOperation;
use App\Models\Plan;
use App\Models\User;
use App\Services\Files\FileProcessor;
use App\Services\Files\ImageProcessor;
use App\Services\Files\JpegPdfWriter;
use App\Services\Quota\QuotaService;
use App\Services\Tier\PlanLimits;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FileToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
        Storage::fake('local');
    }

    private function pdf(string $name, int $pages): UploadedFile
    {
        // Keep a reference: the fake file is deleted when the object is freed.
        $image = UploadedFile::fake()->image('p.jpg', 40, 60);
        $jpeg = app(ImageProcessor::class)->toJpeg($image->getPathname());
        $content = app(JpegPdfWriter::class)->write(array_fill(0, $pages, $jpeg), 'a4', 'portrait', 0);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function pageCount(FileOperation $operation): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', Storage::disk('local')->get($operation->output_path));
    }

    private function requireGhostscript(): void
    {
        exec('command -v '.escapeshellarg(config('toolkits.files.ghostscript')), $out, $code);

        if ($code !== 0) {
            $this->markTestSkipped('Ghostscript tidak terpasang.');
        }
    }

    public function test_image_convert_produces_downloadable_result_for_owner_only(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('Foto Liburan.png', 120, 80)])
            ->set('targetFormat', 'webp')
            ->call('process')
            ->assertHasNoErrors()
            ->call('checkOperation')
            ->assertDispatched('operation-done')
            ->assertSee('Unduh hasil');

        $operation = FileOperation::sole();
        $this->assertSame(FileOperationStatus::Done, $operation->status);
        $this->assertSame('foto-liburan.webp', $operation->output_name);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('local')->path($operation->output_path))[2]);
        Storage::disk('local')->assertMissing($operation->inputs[0]['path']);

        $this->actingAs($user)->get(route('files.download', $operation))->assertOk()->assertDownload('foto-liburan.webp');
        $this->actingAs(User::factory()->create())->get(route('files.download', $operation))->assertNotFound();
    }

    public function test_multiple_results_are_zipped(): void
    {
        Livewire::test(Tools\ImageResize::class)
            ->set('files', [UploadedFile::fake()->image('a.jpg', 200, 100), UploadedFile::fake()->image('b.jpg', 200, 100)])
            ->set('percent', 50)
            ->call('process')
            ->assertHasNoErrors();

        $operation = FileOperation::sole();
        $this->assertSame('toolkits-image-resize.zip', $operation->output_name);

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($operation->output_path));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame([100, 50], array_slice(getimagesizefromstring($zip->getFromName('a-resize.jpg')), 0, 2));
    }

    public function test_image_compress_never_returns_bigger_file(): void
    {
        $file = UploadedFile::fake()->image('x.png', 300, 300);

        Livewire::test(Tools\ImageCompress::class)->set('files', [$file])->set('level', 'high')->call('process')->assertHasNoErrors();

        $operation = FileOperation::sole();
        $this->assertLessThanOrEqual($operation->inputs[0]['size'], $operation->output_size);
    }

    public function test_image_to_pdf_creates_one_page_per_image(): void
    {
        Livewire::test(Tools\ImageToPdf::class)
            ->set('files', [UploadedFile::fake()->image('a.png', 50, 50), UploadedFile::fake()->image('b.jpg', 80, 40)])
            ->call('process')
            ->assertHasNoErrors();

        $operation = FileOperation::sole();
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($operation->output_path));
        $this->assertSame(2, $this->pageCount($operation));
    }

    public function test_wrong_file_type_is_rejected_by_content(): void
    {
        Livewire::test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->createWithContent('fake.png', 'just text')])
            ->call('process')
            ->assertHasErrors('files');

        $this->assertDatabaseCount('file_operations', 0);
    }

    public function test_pdf_merge_needs_two_files(): void
    {
        Livewire::test(Tools\PdfMerge::class)
            ->set('files', [$this->pdf('a.pdf', 1)])
            ->call('process')
            ->assertHasErrors('files');
    }

    public function test_quota_is_enforced(): void
    {
        $user = User::factory()->create();
        $limit = Plan::where('slug', 'free')->value('limits')['file_ops_per_day'];
        DB::table('usage_counters')->insert([
            'id' => (string) Str::uuid7(), 'subject' => 'u:'.$user->id, 'metric' => 'file_ops',
            'period_date' => app(QuotaService::class)->period(), 'used' => $limit, 'updated_at' => now(),
        ]);

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('over.png', 10, 10)])
            ->call('process')
            ->assertHasErrors('quota');

        $this->assertDatabaseCount('file_operations', 0);
    }

    public function test_active_operation_limit(): void
    {
        $user = User::factory()->create();
        FileOperation::create([
            'user_id' => $user->id, 'tool' => 'image-convert', 'status' => FileOperationStatus::Pending,
            'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addHour(),
        ]);
        FileOperation::create([
            'user_id' => $user->id, 'tool' => 'image-convert', 'status' => FileOperationStatus::Processing,
            'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addHour(),
        ]);

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('a.png', 10, 10)])
            ->call('process')
            ->assertHasErrors('files');
    }

    private function pendingOperation(User $user, string $tool = 'image-convert'): FileOperation
    {
        Storage::disk('local')->put('file-ops/x/in-001.png', 'data');

        $operation = FileOperation::create([
            'user_id' => $user->id, 'tool' => $tool, 'status' => FileOperationStatus::Pending,
            'options' => ['targetFormat' => 'webp'], 'inputs' => [], 'quota_subjects' => ['u:'.$user->id], 'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->move('file-ops/x/in-001.png', $operation->directory().'/in-001.png');
        app(QuotaService::class)->reserve(['u:'.$user->id], 'file_ops', 1, ['u:'.$user->id => null]);

        return $operation;
    }

    public function test_reopening_tool_page_restores_running_operation(): void
    {
        $user = User::factory()->create();
        $operation = $this->pendingOperation($user);

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->assertSet('operationId', $operation->id)
            ->assertSee('Menunggu antrean')
            ->assertSee('Batalkan');

        // Other tools are not hijacked by it.
        Livewire::actingAs($user)->test(Tools\ImageCompress::class)->assertSet('operationId', null);
    }

    public function test_capacity_error_names_the_busy_tool(): void
    {
        $user = User::factory()->create();
        $this->pendingOperation($user, 'pdf-merge');
        $this->pendingOperation($user, 'pdf-merge');

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('a.png', 10, 10)])
            ->call('process')
            ->assertHasErrors('files')
            ->assertSee('Masih ada proses yang berjalan di Gabung PDF');
    }

    public function test_cancel_pending_operation_refunds_quota_and_deletes_files(): void
    {
        $user = User::factory()->create();
        $operation = $this->pendingOperation($user);

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->call('cancelOperation')
            ->assertHasNoErrors()
            ->assertSet('operationId', null);

        $this->assertSame(FileOperationStatus::Cancelled, $operation->fresh()->status);
        $this->assertSame(0, (int) DB::table('usage_counters')->where('subject', 'u:'.$user->id)->value('used'));
        Storage::disk('local')->assertMissing($operation->directory().'/in-001.png');

        // A worker picking up the cancelled job does nothing.
        (new ProcessFileOperation($operation->fresh()))->handle(app(FileProcessor::class));
        $this->assertSame(FileOperationStatus::Cancelled, $operation->fresh()->status);
    }

    public function test_processing_operation_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $operation = $this->pendingOperation($user);
        $operation->forceFill(['status' => FileOperationStatus::Processing])->save();

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->assertDontSee('Batalkan')
            ->call('cancelOperation')
            ->assertHasErrors('files');

        $this->assertSame(FileOperationStatus::Processing, $operation->fresh()->status);
    }

    public function test_pdf_tools_with_ghostscript(): void
    {
        $this->requireGhostscript();

        Livewire::test(Tools\PdfMerge::class)->set('files', [$this->pdf('a.pdf', 2), $this->pdf('b.pdf', 1)])->call('process')->assertHasNoErrors();
        $merge = FileOperation::latest('id')->first();
        $this->assertSame(FileOperationStatus::Done, $merge->status, (string) $merge->error);
        $this->assertSame(3, $this->pageCount($merge));

        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Tools\PdfSplit::class)->set('files', [$this->pdf('c.pdf', 3)])->set('mode', 'extract')->set('ranges', '2-3')->call('process');
        $extract = FileOperation::where('user_id', $user->id)->sole();
        $this->assertSame(2, $this->pageCount($extract));

        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Tools\PdfSplit::class)->set('files', [$this->pdf('c.pdf', 3)])->set('mode', 'each')->call('process');
        $each = FileOperation::where('user_id', $user->id)->sole();
        $this->assertStringEndsWith('.zip', $each->output_name);

        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Tools\PdfCompress::class)->set('files', [$this->pdf('d.pdf', 2)])->call('process');
        $this->assertSame(FileOperationStatus::Done, FileOperation::where('user_id', $user->id)->sole()->status);
    }

    // Regression: a low-dpi, high-quality JPEG used to pass through Ghostscript unchanged.
    public function test_pdf_compress_shrinks_already_low_dpi_images(): void
    {
        $this->requireGhostscript();

        $image = imagecreatetruecolor(620, 877);
        for ($y = 0; $y < 877; $y += 3) {
            for ($x = 0; $x < 620; $x += 3) {
                imagefilledrectangle($image, $x, $y, $x + 2, $y + 2, imagecolorallocate($image, ($x * 7 + $y) % 256, ($y * 3) % 256, ($x ^ $y) % 256));
            }
        }
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = ['data' => (string) ob_get_clean(), 'width' => 620, 'height' => 877];
        $pdf = UploadedFile::fake()->createWithContent('scan.pdf', app(JpegPdfWriter::class)->write([$jpeg], 'a4', 'portrait', 0));

        Livewire::test(Tools\PdfCompress::class)->set('files', [$pdf])->set('level', 'light')->call('process')->assertHasNoErrors();

        $operation = FileOperation::sole();
        $this->assertSame(FileOperationStatus::Done, $operation->status, (string) $operation->error);
        $this->assertLessThan($operation->inputSize() * 0.8, $operation->output_size);
    }

    public function test_result_preview_is_small_watermarked_jpeg_for_owner_only(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('a.png', 1600, 1200)])->set('targetFormat', 'png')
            ->call('process')
            ->assertSee(route('files.preview', [FileOperation::sole(), 0]));

        $operation = FileOperation::sole();
        $response = $this->actingAs($user)->get(route('files.preview', [$operation, 0]))->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Never the result itself: different format, at most 480 px.
        $preview = $response->streamedContent();
        $this->assertNotSame(Storage::disk('local')->get($operation->output_path), $preview);
        [$w, $h] = getimagesizefromstring($preview);
        $this->assertSame([480, 360], [$w, $h]);

        $this->actingAs($user)->get(route('files.preview', [$operation, 1]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('files.preview', [$operation, 0]))->assertNotFound();

        $this->travelTo($operation->expires_at->addMinute());
        $this->actingAs($user)->get(route('files.preview', [$operation, 0]))->assertStatus(410);
    }

    public function test_zip_result_has_one_preview_per_file(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Tools\ImageResize::class)
            ->set('files', [UploadedFile::fake()->image('a.jpg', 200, 100), UploadedFile::fake()->image('b.jpg', 200, 100)])
            ->set('percent', 50)
            ->call('process');

        $operation = FileOperation::sole();
        $this->assertSame([['index' => 0, 'label' => 'a-resize.jpg'], ['index' => 1, 'label' => 'b-resize.jpg']], $component->get('previews'));
        $this->actingAs($user)->get(route('files.preview', [$operation, 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($user)->get(route('files.preview', [$operation, 2]))->assertNotFound();
    }

    public function test_pdf_results_are_previewed_as_page_images(): void
    {
        $this->requireGhostscript();

        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Tools\PdfMerge::class)
            ->set('files', [$this->pdf('a.pdf', 2), $this->pdf('b.pdf', 1)])->call('process');
        $this->assertSame(['1', '2', '3'], array_column($component->get('previews'), 'label'));
        $this->actingAs($user)->get(route('files.preview', [FileOperation::sole(), 2]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Tools\PdfSplit::class)
            ->set('files', [$this->pdf('c.pdf', 3)])->set('mode', 'each')->call('process');
        $this->assertSame(['halaman-001.pdf', 'halaman-002.pdf', 'halaman-003.pdf'], array_column($component->get('previews'), 'label'));
    }

    public function test_failed_operation_shows_error_and_refunds_quota(): void
    {
        $this->requireGhostscript();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Tools\PdfSplit::class)
            ->set('files', [$this->pdf('c.pdf', 1)])->set('mode', 'extract')->set('ranges', '5')
            ->call('process')
            ->assertSee('Halaman yang dipilih tidak ada di PDF.');

        $this->assertSame(FileOperationStatus::Failed, FileOperation::sole()->status);
        $this->assertSame(0, (int) DB::table('usage_counters')->where('subject', 'u:'.$user->id)->value('used'));
    }

    public function test_prune_removes_expired_files(): void
    {
        Livewire::test(Tools\ImageConvert::class)->set('files', [UploadedFile::fake()->image('a.png', 10, 10)])->call('process');
        $operation = FileOperation::sole();

        $this->travelTo($operation->expires_at->addMinute());
        $this->artisan('files:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing($operation->output_path);
        $this->assertSame(FileOperationStatus::Expired, $operation->fresh()->status);
    }

    public function test_dashboard_lists_own_results_only(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->set('files', [UploadedFile::fake()->image('Foto Liburan.png', 20, 20)])
            ->set('targetFormat', 'webp')
            ->call('process');

        $other = User::factory()->create();
        FileOperation::create([
            'user_id' => $other->id, 'tool' => 'image-convert', 'status' => FileOperationStatus::Done,
            'inputs' => [['path' => 'x', 'name' => 'rahasia.png', 'size' => 1]], 'quota_subjects' => [], 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)->get(route('dashboard.files.index'))
            ->assertOk()
            ->assertSee('foto-liburan.webp')
            ->assertSee('Unduh')
            ->assertDontSee('rahasia.png');
    }

    public function test_dashboard_deletes_finished_result_but_not_running_or_foreign(): void
    {
        $user = User::factory()->create();
        $running = $this->pendingOperation($user);

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)->call('cancelOperation');
        $finished = $running->fresh();
        Storage::disk('local')->put($finished->directory().'/out.webp', 'data');

        $active = $this->pendingOperation($user, 'pdf-merge');
        $foreign = $this->pendingOperation(User::factory()->create());
        $foreign->forceFill(['status' => FileOperationStatus::Done])->save();

        $component = Livewire::actingAs($user)->test(Client\Files\Index::class)
            ->call('delete', $finished->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($finished);
        Storage::disk('local')->assertMissing($finished->directory().'/out.webp');

        $component->call('delete', $active->id)->assertHasErrors('files');
        $this->assertModelExists($active);

        $component->call('delete', $foreign->id)->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_login_claims_guest_results_with_account_retention(): void
    {
        $guestId = (string) Str::uuid();
        $user = User::factory()->create();
        $operation = FileOperation::create([
            'guest_id' => $guestId, 'tool' => 'image-convert', 'status' => FileOperationStatus::Done,
            'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addMinutes(15),
        ]);
        $otherGuest = FileOperation::create([
            'guest_id' => (string) Str::uuid(), 'tool' => 'image-convert', 'status' => FileOperationStatus::Done,
            'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addMinutes(15),
        ]);

        $this->withCookie('tk_gid', $guestId)
            ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $operation->refresh();
        $this->assertSame($user->id, $operation->user_id);
        $retention = Plan::query()->where('slug', 'free')->value('limits')['retention_minutes'];
        $this->assertTrue($operation->expires_at->greaterThanOrEqualTo($operation->created_at->addMinutes($retention)->subSecond()));
        $this->assertNull($otherGuest->fresh()->user_id);
    }

    public function test_empty_file_limits_mean_system_cap_no_concurrency_cap_and_permanent_results(): void
    {
        $plan = Plan::query()->where('slug', 'free')->first();
        $plan->forceFill(['limits' => [...$plan->limits, 'max_file_mb' => null, 'max_files_per_op' => null, 'max_active_ops' => null, 'retention_minutes' => null]])->save();
        app(PlanLimits::class)->flush();
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            FileOperation::create([
                'user_id' => $user->id, 'tool' => 'image-convert', 'status' => FileOperationStatus::Pending,
                'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addHour(),
            ]);
        }

        Livewire::actingAs($user)->test(Tools\ImageConvert::class)
            ->assertSee('Maks 100 file, 100 MB / file')
            ->set('files', [UploadedFile::fake()->image('a.png', 10, 10)->size(3 * 1024)])
            ->call('process')
            ->assertHasNoErrors()
            ->call('checkOperation')
            ->assertSee('Disimpan permanen');

        $operation = FileOperation::query()->where('status', FileOperationStatus::Done->value)->sole();
        $this->assertNull($operation->expires_at);

        $this->travel(5)->years();
        $this->artisan('files:prune')->assertSuccessful();

        $this->assertSame(FileOperationStatus::Done, $operation->fresh()->status);
        $this->actingAs($user)->get(route('files.download', $operation))->assertOk();
    }

    public function test_login_claim_keeps_results_permanently_when_account_retention_is_empty(): void
    {
        $plan = Plan::query()->where('slug', 'free')->first();
        $plan->forceFill(['limits' => [...$plan->limits, 'retention_minutes' => null]])->save();
        app(PlanLimits::class)->flush();
        $guestId = (string) Str::uuid();
        $user = User::factory()->create();
        $operation = FileOperation::create([
            'guest_id' => $guestId, 'tool' => 'image-convert', 'status' => FileOperationStatus::Done,
            'inputs' => [], 'quota_subjects' => [], 'expires_at' => now()->addMinutes(15),
        ]);

        $this->withCookie('tk_gid', $guestId)
            ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertSame([$user->id, null], [$operation->fresh()->user_id, $operation->fresh()->expires_at]);
    }
}
