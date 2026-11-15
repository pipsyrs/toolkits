<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\HandlesFileTool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Gambar ke PDF')]
class ImageToPdf extends Component
{
    use HandlesFileTool;

    public string $pageSize = 'a4';

    public string $orientation = 'portrait';

    public int $margin = 0;

    protected function toolSlug(): string
    {
        return 'image-to-pdf';
    }

    protected function optionRules(): array
    {
        return [
            'pageSize' => ['required', 'in:a4,letter,fit'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'margin' => ['required', 'integer', 'in:0,10,20'],
        ];
    }

    public function render(): View
    {
        return view('livewire.tools.image-to-pdf');
    }
}
