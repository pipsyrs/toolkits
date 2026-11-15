<?php

namespace App\Livewire\Admin\Settings;

use App\Actions\Admin\SendTestMail;
use App\Actions\Admin\UpdateAppSettings;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingsRepository;
use App\Support\PasswordConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::admin')]
#[Title('Pengaturan')]
class Edit extends Component
{
    use AuthorizesAdmin;

    public const PERMISSION = 'settings.manage';

    /** @var array<string, array<string, mixed>> non-secret only, nested by group */
    public array $values = [];

    /** @var array<string, array<string, string>> write-only, always empty after mount/save */
    public array $secrets = [];

    /** @var array<string, array<string, bool>> */
    public array $clear = [];

    public function mount(): void
    {
        $this->fillFromStore();
    }

    public function save(UpdateAppSettings $action): void
    {
        if (! PasswordConfirmation::recent()) {
            redirect()->setIntendedUrl(route('admin.settings.edit'));
            $this->redirectRoute('password.confirm');

            return;
        }

        try {
            $changes = $action->handle($this->adminUser(), $this->values, $this->secrets, $this->clear, request()->ip());
        } finally {
            // Never keep typed secrets in component state.
            $this->resetSecrets();
        }

        $this->fillFromStore();

        session()->flash('status', $changes === [] ? 'Tidak ada perubahan.' : 'Pengaturan disimpan.');
    }

    public function sendTestMail(SendTestMail $action): void
    {
        // Unsaved typed secrets are not echoed back in the snapshot.
        $this->resetSecrets();

        try {
            $action->handle($this->adminUser());
            session()->flash('status', 'Email uji dikirim ke '.$this->adminUser()->email.'.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }
    }

    public function render(): View
    {
        $registry = app(SettingRegistry::class);
        $settings = app(SettingsRepository::class);
        $groups = [];

        foreach ($registry->grouped() as $group => $items) {
            foreach ($items as $key => $item) {
                $secret = $item['type'] === 'secret';
                $env = $settings->envValue($key);

                $groups[$group][$key] = [
                    'label' => $item['label'],
                    'type' => $item['type'],
                    'options' => $item['options'],
                    'help' => $item['help'],
                    'source' => $settings->source($key),
                    'masked' => $secret ? $settings->masked($key) : null,
                    'envPlaceholder' => $secret || ! is_scalar($env) ? null : (string) $env,
                ];
            }
        }

        return view('livewire.admin.settings.edit', [
            'groups' => array_filter($groups),
            'decryptErrors' => $settings->hasDecryptErrors(),
        ]);
    }

    protected function fillFromStore(): void
    {
        $registry = app(SettingRegistry::class);
        $settings = app(SettingsRepository::class);
        $values = [];

        foreach ($registry->all() as $key => $item) {
            if ($item['type'] !== 'secret') {
                data_set($values, $key, $settings->get($key) ?? ($item['type'] === 'bool' ? false : ''));
            }
        }

        $this->values = $values;
        $this->resetSecrets();
    }

    protected function resetSecrets(): void
    {
        $secrets = [];
        $clear = [];

        foreach (app(SettingRegistry::class)->all() as $key => $item) {
            if ($item['type'] === 'secret') {
                data_set($secrets, $key, '');
                data_set($clear, $key, false);
            }
        }

        $this->secrets = $secrets;
        $this->clear = $clear;
    }
}
