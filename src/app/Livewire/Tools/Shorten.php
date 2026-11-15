<?php

namespace App\Livewire\Tools;

use App\Actions\Links\CreateShortLink;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Concerns\RequiresActiveTool;
use App\Livewire\Concerns\ResolvesVisitor;
use App\Services\Quota\QuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Pemendek URL')]
class Shorten extends Component
{
    use RequiresActiveTool, ResolvesVisitor;

    public string $targetUrl = '';

    public ?string $alias = null;

    // Pro only.
    public ?string $expiresAt = null;

    /** @var array{code: string, short_url: string, expires_at: string|null}|null */
    #[Locked]
    public ?array $result = null;

    private const FIELD_MAP = [
        'target_url' => 'targetUrl',
        'alias' => 'alias',
        'expires_at' => 'expiresAt',
    ];

    #[Computed]
    public function remaining(): ?int
    {
        return app(QuotaService::class)
            ->summary($this->visitor(), $this->guestId(), $this->clientIp(), CreateShortLink::METRIC)['remaining'];
    }

    public function shorten(CreateShortLink $action): void
    {
        $this->resetErrorBag();
        $this->result = null;

        $this->validate([
            'targetUrl' => ['required', 'string', 'max:2048'],
            'alias' => ['nullable', 'string', 'max:30'],
            'expiresAt' => ['nullable', 'string', 'max:32'],
        ], [], ['targetUrl' => 'URL tujuan', 'expiresAt' => 'tanggal kedaluwarsa']);

        try {
            $link = $action->handle($this->visitor(), $this->guestId(), $this->clientIp(), [
                'target_url' => $this->targetUrl,
                'alias' => $this->alias,
                'expires_at' => $this->expiresAt,
            ]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($messages, $key) => [self::FIELD_MAP[$key] ?? $key => $messages])
                ->all());
        } catch (QuotaExceededException $e) {
            $this->addError('quota', $e->getMessage());

            return;
        }

        $this->result = [
            'code' => $link->code,
            'short_url' => $link->short_url,
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];

        $this->reset('targetUrl', 'alias', 'expiresAt');
        unset($this->remaining);
        $this->dispatch('operation-done');
    }

    protected function toolSlug(): string
    {
        return 'shorten';
    }

    public function render(): View
    {
        return view('livewire.tools.shorten');
    }
}
