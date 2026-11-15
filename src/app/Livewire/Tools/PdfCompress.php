<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Kompres PDF')]
class PdfCompress extends Component
{
    use HandlesFileTool;

    public string $level = 'recommended';

    protected function toolSlug(): string
    {
        return 'pdf-compress';
    }

    protected function optionRules(): array
    {
        return [
            'level' => ['required', 'in:light,recommended,strong'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.pdf-compress');
    }
}
