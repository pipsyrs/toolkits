<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Ubah Ukuran Gambar')]
class ImageResize extends Component
{
    use HandlesFileTool;

    public string $mode = 'percent';

    public int $percent = 50;

    public ?int $width = null;

    public ?int $height = null;

    public bool $keepAspect = true;

    protected function toolSlug(): string
    {
        return 'image-resize';
    }

    protected function optionRules(): array
    {
        return [
            'mode' => ['required', 'in:percent,dimension'],
            'percent' => ['exclude_unless:mode,percent', 'required', 'integer', 'min:1', 'max:100'],
            'width' => ['exclude_unless:mode,dimension', 'nullable', 'required_without:height', 'integer', 'min:1', 'max:10000'],
            'height' => ['exclude_unless:mode,dimension', 'nullable', 'required_without:width', 'integer', 'min:1', 'max:10000'],
            'keepAspect' => ['boolean'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.image-resize');
    }
}
