<?php

namespace App\Livewire\Concerns;

use App\Enums\ToolStatus;
use App\Services\Tools\ToolCatalog;
use Illuminate\Http\Exceptions\HttpResponseException;
use Livewire\Livewire;

// Blocks the page and every Livewire action while the tool is not live.
trait RequiresActiveTool
{
    abstract protected function toolSlug(): string;

    public function bootRequiresActiveTool(): void
    {
        $tool = app(ToolCatalog::class)->find($this->toolSlug());
        $status = $tool ? ToolStatus::from($tool['status']) : ToolStatus::Disabled;

        if ($status === ToolStatus::Live) {
            return;
        }

        // Action on an already open page (status changed meanwhile).
        abort_if(Livewire::isLivewireRequest(), 503, $status->message());

        throw new HttpResponseException(
            response()->view('pages.tools.unavailable', ['tool' => $tool, 'status' => $status], 503)
        );
    }
}
