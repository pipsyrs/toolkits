<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Gabung PDF')]
class PdfMerge extends Component
{
    use HandlesFileTool;

    protected function toolSlug(): string
    {
        return 'pdf-merge';
    }

    protected function optionRules(): array
    {
        return [];
    }

    public function render(): View
    {
        return view('livewire.tools.pdf-merge');
    }
}
