<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Konversi Gambar')]
class ImageConvert extends Component
{
    use HandlesFileTool;

    public string $targetFormat = 'webp';

    protected function toolSlug(): string
    {
        return 'image-convert';
    }

    protected function optionRules(): array
    {
        return [
            'targetFormat' => ['required', 'in:jpg,png,webp'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.image-convert');
    }
}
