<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Kompres Gambar')]
class ImageCompress extends Component
{
    use HandlesFileTool;

    public string $level = 'medium';

    protected function toolSlug(): string
    {
        return 'image-compress';
    }

    protected function optionRules(): array
    {
        return [
            'level' => ['required', 'in:low,medium,high'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.image-compress');
    }
}
