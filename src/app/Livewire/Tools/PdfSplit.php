<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Pisah PDF')]
class PdfSplit extends Component
{
    use HandlesFileTool;

    public string $mode = 'extract';

    public string $ranges = '';

    protected function toolSlug(): string
    {
        return 'pdf-split';
    }

    protected function optionRules(): array
    {
        return [
            'mode' => ['required', 'in:extract,each'],
            'ranges' => ['exclude_unless:mode,extract', 'required', 'string', 'max:200', 'regex:/^\\d+(-\\d+)?(,\\d+(-\\d+)?)*$/'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.pdf-split');
    }
}
