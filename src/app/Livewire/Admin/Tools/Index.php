<?php

namespace App\Livewire\Admin\Tools;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Services\Tools\ToolCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Tool list; each tool's settings live on its own page (Show).
#[Layout('layouts::admin')]
#[Title('Alat')]
class Index extends Component
{
    use AuthorizesAdmin;

    public const PERMISSION = 'tools.manage';

    public function render(ToolCatalog $catalog): View
    {
        return view('livewire.admin.tools.index', ['tools' => $catalog->all()]);
    }
}
