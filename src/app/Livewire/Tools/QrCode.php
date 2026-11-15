<?php

namespace App\Livewire\Tools;

use App\Enums\QrFrame;
use App\Enums\QrTemplate;
use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\RequiresActiveTool;
use App\Livewire\Concerns\ResolvesVisitor;
use App\Services\Qr\QrStyle;
use App\Services\QrCodeService;
use App\Services\Quota\QuotaService;
use App\Services\Tools\ToolCatalog;
use App\Support\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('QR Code Generator')]
class QrCode extends Component
{
    use GuardsPropertyTypes, RequiresActiveTool, ResolvesVisitor;

    private const GENERATE_PER_MINUTE = 30;

    // Preview follows typing (debounced), so it gets a separate, larger budget.
    private const PREVIEW_PER_MINUTE = 120;

    private const PREVIEW_SIZE = 240;

    // Pro features of this tool (config toolkits.tools.*.features); admin can make each one free.
    private const FEATURES = ['svg', 'premium_template', 'premium_frame', 'custom_color'];

    // Property => color key of QrStyle.
    private const COLOR_PROPERTIES = ['colorDark' => 'dark', 'colorEye' => 'eye', 'colorBg' => 'bg', 'colorFrame' => 'frame'];

    // Any change here makes the last download stale.
    private const OUTPUT_PROPERTIES = ['text', 'format', 'size', 'ecc', 'template', 'frame', 'frameTitle', 'frameFooter', 'colorDark', 'colorEye', 'colorBg', 'colorFrame'];

    public string $text = '';

    public string $format = 'png';

    public int $size = 300;

    public string $ecc = 'M';

    public string $template = 'classic';

    public string $frame = 'none';

    public string $frameTitle = '';

    public string $frameFooter = '';

    // Start from the template palette; differing values count as custom colors (Pro feature).
    public string $colorDark = '';

    public string $colorEye = '';

    public string $colorBg = '';

    public string $colorFrame = '';

    // PNG or SVG as data URI for <img src>.
    #[Locked]
    public ?string $dataUri = null;

    // Raw SVG markup (download only, never render unescaped).
    #[Locked]
    public ?string $svg = null;

    // Live look-alike that cannot be scanned (see QrCodeService::previewPng); the real code only comes from generate().
    #[Locked]
    public ?string $previewUri = null;

    public function mount(): void
    {
        $this->applyTemplateColors();
        $text = request()->query('text');

        if (is_string($text)) {
            $this->text = mb_substr($text, 0, (int) config('toolkits.qr.max_text'));
            $this->refreshPreview();
        }
    }

    public function updated(string $property): void
    {
        if (! in_array($property, self::OUTPUT_PROPERTIES, true)) {
            return;
        }

        if ($property === 'template') {
            $this->applyTemplateColors();
        }

        $this->outputChanged();
    }

    public function resetColors(): void
    {
        $this->applyTemplateColors();
        $this->outputChanged();
    }

    /**
     * Feature => true when the visitor cannot use it.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function locked(): array
    {
        $catalog = app(ToolCatalog::class);

        return array_combine(self::FEATURES, array_map(
            fn (string $feature) => ! $catalog->canUseFeature($this->toolSlug(), $feature, $this->tier),
            self::FEATURES,
        ));
    }

    // Live hint while picking colors; generate() enforces it.
    #[Computed]
    public function lowContrast(): bool
    {
        $style = $this->currentStyle();

        return $style !== null && $style->lowContrastColors() !== [];
    }

    public function generate(QrCodeService $qr): void
    {
        $key = $this->rateKey('qr');

        if (RateLimiter::tooManyAttempts($key, self::GENERATE_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'text' => 'Terlalu banyak permintaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $this->validate($this->qrRules(), [
            'frameTitle.not_regex' => 'Judul berisi karakter yang tidak didukung.',
            'frameFooter.not_regex' => 'Footer berisi karakter yang tidak didukung.',
            'color*.regex' => 'Warna tidak valid.',
        ], ['text' => 'teks', 'size' => 'ukuran', 'frameTitle' => 'judul', 'frameFooter' => 'footer']);

        $style = $this->buildStyle();
        $errors = [...$this->lockedErrors($style), ...$this->contrastErrors($style)];

        if ($this->format === 'svg' && $this->locked['svg']) {
            $errors['format'] = 'Format SVG hanya untuk akun Pro.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->svg = null;

        if ($this->format === 'svg') {
            $this->svg = $qr->svg($this->text, $this->size, $this->ecc, $style);
            $this->dataUri = $qr->svgDataUri($this->svg);
        } else {
            $this->dataUri = $qr->png($this->text, $this->size, $this->ecc, $style);
        }

        $this->dispatch('operation-done');
    }

    // The download must always match the current settings.
    private function outputChanged(): void
    {
        $this->dataUri = null;
        $this->svg = null;
        $this->refreshPreview();
    }

    // Silent: errors are shown when the visitor presses "Buat QR", not while typing.
    private function refreshPreview(): void
    {
        $this->previewUri = null;
        $input = ['text' => $this->text, 'ecc' => $this->ecc];

        if (Validator::make($input, Arr::only($this->qrRules(), ['text', 'ecc']))->fails()) {
            return;
        }

        // Locked styles get no preview either, even from a tampered request.
        $style = $this->currentStyle();

        if ($style === null || $this->lockedErrors($style) !== []) {
            return;
        }

        $key = $this->rateKey('qr-preview');

        if (RateLimiter::tooManyAttempts($key, self::PREVIEW_PER_MINUTE)) {
            return;
        }

        RateLimiter::hit($key, 60);
        $this->previewUri = app(QrCodeService::class)->previewPng($this->text, self::PREVIEW_SIZE, $this->ecc, $style);
    }

    // Null while any style field is invalid.
    private function currentStyle(): ?QrStyle
    {
        $fields = array_keys(Arr::except($this->qrRules(), ['text', 'format', 'size', 'ecc']));

        return Validator::make($this->only($fields), Arr::only($this->qrRules(), $fields))->fails() ? null : $this->buildStyle();
    }

    // Call only after the style fields passed qrRules().
    private function buildStyle(): QrStyle
    {
        $colors = [];

        foreach (self::COLOR_PROPERTIES as $property => $key) {
            $colors[$key] = strtolower($this->{$property});
        }

        return new QrStyle(QrTemplate::from($this->template), $colors, QrFrame::from($this->frame), trim($this->frameTitle), trim($this->frameFooter));
    }

    /**
     * @return array<string, string>
     */
    private function lockedErrors(QrStyle $style): array
    {
        return array_filter([
            'template' => $style->template->isPro() && $this->locked['premium_template'] ? 'Template ini hanya untuk akun Pro.' : null,
            'frame' => $style->frame->isPro() && $this->locked['premium_frame'] ? 'Bingkai ini hanya untuk akun Pro.' : null,
            'colors' => $style->hasCustomColors() && $this->locked['custom_color'] ? 'Warna kustom hanya untuk akun Pro.' : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function contrastErrors(QrStyle $style): array
    {
        return $style->lowContrastColors() === []
            ? []
            : ['colors' => 'Warna modul harus jauh lebih gelap dari latar agar QR bisa dipindai.'];
    }

    private function applyTemplateColors(): void
    {
        $palette = (QrTemplate::tryFrom($this->template) ?? QrTemplate::Classic)->colors();

        foreach (self::COLOR_PROPERTIES as $property => $key) {
            $this->{$property} = $palette[$key];
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function qrRules(): array
    {
        $frameText = ['nullable', 'string', 'max:'.QrStyle::MAX_FRAME_TEXT, 'not_regex:'.QrStyle::FORBIDDEN_TEXT];
        $color = ['required', 'string', 'regex:'.Color::HEX_PATTERN];

        return [
            'text' => ['required', 'string', 'max:'.(int) config('toolkits.qr.max_text')],
            'format' => ['required', 'in:png,svg'],
            'size' => ['required', 'integer', 'min:200', 'max:1000'],
            'ecc' => ['required', 'in:'.implode(',', QrCodeService::ECC_LEVELS)],
            'template' => ['required', Rule::enum(QrTemplate::class)],
            'frame' => ['required', Rule::enum(QrFrame::class)],
            'frameTitle' => $frameText,
            'frameFooter' => $frameText,
            ...array_fill_keys(array_keys(self::COLOR_PROPERTIES), $color),
        ];
    }

    // Not keyed by guest cookie (client can drop it).
    private function rateKey(string $prefix): string
    {
        $user = $this->visitor();

        return $user !== null
            ? "{$prefix}:u:".$user->getKey()
            : "{$prefix}:ip:".app(QuotaService::class)->normalizeIp($this->clientIp());
    }

    protected function toolSlug(): string
    {
        return 'qr-code';
    }

    public function render(): View
    {
        return view('livewire.tools.qr-code');
    }
}
