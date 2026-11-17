<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\GuardsPropertyTypes;
use App\Livewire\Concerns\HandlesFileTool;
use App\Services\Tools\ToolCatalog;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Hapus Background')]
class RemoveBackground extends Component
{
    use GuardsPropertyTypes, HandlesFileTool;

    private const FEATURE = 'crop';

    // Smallest crop side, relative to the image; keeps the crop from collapsing to a few pixels.
    private const MIN_CROP = 0.01;

    // Float rounding from the browser cropper may push x + w a hair past 1.
    private const EDGE_TOLERANCE = 0.001;

    public const MAX_PADDING = 200;

    public bool $crop = false;

    public float $cropX = 0;

    public float $cropY = 0;

    public float $cropW = 1;

    public float $cropH = 1;

    public bool $trim = false;

    public int $padding = 0;

    protected function toolSlug(): string
    {
        return 'remove-bg';
    }

    protected function optionRules(): array
    {
        return [
            'crop' => ['boolean', $this->requiresFeature()],
            'cropX' => ['exclude_unless:crop,true', 'required', 'numeric', 'between:0,1'],
            'cropY' => ['exclude_unless:crop,true', 'required', 'numeric', 'between:0,1'],
            'cropW' => ['exclude_unless:crop,true', 'required', 'numeric', 'between:'.self::MIN_CROP.',1', $this->fitsWithin('cropX')],
            'cropH' => ['exclude_unless:crop,true', 'required', 'numeric', 'between:'.self::MIN_CROP.',1', $this->fitsWithin('cropY')],
            'trim' => ['boolean', $this->requiresFeature()],
            'padding' => ['exclude_unless:trim,true', 'required', 'integer', 'min:0', 'max:'.self::MAX_PADDING],
        ];
    }

    #[Computed]
    public function cropLocked(): bool
    {
        return ! app(ToolCatalog::class)->canUseFeature($this->toolSlug(), self::FEATURE, $this->tier);
    }

    // Paid option: enforced here, the disabled panel in the view is only a hint.
    private function requiresFeature(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value && $this->cropLocked) {
                $fail('Pengaturan crop hanya tersedia di paket berbayar.');
            }
        };
    }

    private function fitsWithin(string $offset): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($offset) {
            if ((float) $this->{$offset} + (float) $value > 1 + self::EDGE_TOLERANCE) {
                $fail('Area crop keluar dari batas gambar.');
            }
        };
    }

    public function render(): View
    {
        return view('livewire.tools.remove-bg');
    }
}
