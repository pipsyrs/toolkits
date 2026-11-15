<?php

namespace App\Services\Tier;

use Illuminate\Validation\Rule;

// Fixed catalog of plan limit keys: the rows of the admin plan matrix. Only these keys are written from the admin UI.
class Entitlements
{
    public const BOOL = 'bool';

    public const INT = 'int';

    public const SELECT = 'select';

    // Matches the Livewire upload rule (config/livewire.php, max:102400 KB).
    private const MAX_UPLOAD_MB = 100;

    private const MAX_FILES_PER_OP = 100;

    /**
     * Keys enforced by the app. nullable int: empty = unlimited.
     *
     * @var array<string, array{group: string, label: string, type: string, default: mixed, nullable?: bool, min?: int, max?: int, options?: array<string, string>, help?: string}>
     */
    private const KEYS = [
        'max_file_mb' => ['group' => 'File', 'label' => 'Ukuran file maks (MB)', 'type' => self::INT, 'nullable' => true, 'min' => 1, 'max' => self::MAX_UPLOAD_MB, 'default' => 2, 'help' => 'Kosong = maks sistem ('.self::MAX_UPLOAD_MB.' MB)'],
        'max_files_per_op' => ['group' => 'File', 'label' => 'File per proses', 'type' => self::INT, 'nullable' => true, 'min' => 1, 'max' => self::MAX_FILES_PER_OP, 'default' => 1, 'help' => 'Kosong = maks sistem ('.self::MAX_FILES_PER_OP.' file)'],
        'file_ops_per_day' => ['group' => 'File', 'label' => 'Proses file per hari', 'type' => self::INT, 'nullable' => true, 'min' => 0, 'max' => 1000000, 'default' => 0],
        'max_active_ops' => ['group' => 'File', 'label' => 'Proses berjalan bersamaan', 'type' => self::INT, 'nullable' => true, 'min' => 1, 'max' => 20, 'default' => 1],
        'retention_minutes' => ['group' => 'File', 'label' => 'Masa simpan hasil (menit)', 'type' => self::INT, 'nullable' => true, 'min' => 1, 'max' => 525600, 'default' => 15, 'help' => 'Kosong = permanen'],
        'queue' => ['group' => 'File', 'label' => 'Antrean proses', 'type' => self::SELECT, 'options' => ['default' => 'Standar', 'pro' => 'Prioritas'], 'default' => 'default'],
        'links_per_day' => ['group' => 'Link', 'label' => 'Link baru per hari', 'type' => self::INT, 'nullable' => true, 'min' => 0, 'max' => 1000000, 'default' => 0],
        'links_active_max' => ['group' => 'Link', 'label' => 'Link aktif maks', 'type' => self::INT, 'nullable' => true, 'min' => 0, 'max' => 1000000, 'default' => 0],
        'link_lifetime_days' => ['group' => 'Link', 'label' => 'Umur link (hari)', 'type' => self::INT, 'nullable' => true, 'min' => 1, 'max' => 3650, 'default' => 30, 'help' => 'Kosong = permanen'],
        'link_custom_expiry' => ['group' => 'Link', 'label' => 'Atur kedaluwarsa link', 'type' => self::BOOL, 'default' => false],
        'interstitial' => ['group' => 'Link', 'label' => 'Halaman jeda sebelum redirect', 'type' => self::BOOL, 'default' => true],
        'ads' => ['group' => 'Umum', 'label' => 'Tampilkan iklan', 'type' => self::BOOL, 'default' => true],
    ];

    public static function toolKey(string $tool, string $feature): string
    {
        return 'tool_'.str_replace('-', '_', $tool).'_'.$feature;
    }

    /**
     * Upload limit in force: max_file_mb or max_files_per_op, empty = the system maximum (uploads stay bounded).
     *
     * @param  array<string, mixed>  $limits
     */
    public static function uploadLimit(array $limits, string $key): int
    {
        $entry = self::KEYS[$key];
        $value = array_key_exists($key, $limits) ? $limits[$key] : $entry['default'];

        return $value === null ? $entry['max'] : (int) $value;
    }

    /**
     * Minutes a result is kept; null = permanently.
     *
     * @param  array<string, mixed>  $limits
     */
    public static function retentionMinutes(array $limits): ?int
    {
        $value = array_key_exists('retention_minutes', $limits) ? $limits['retention_minutes'] : self::KEYS['retention_minutes']['default'];

        return $value === null ? null : max(1, (int) $value);
    }

    /**
     * Base keys plus one switch per gated tool feature (config toolkits.tools.*.features).
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $keys = self::KEYS;

        foreach (config('toolkits.tools', []) as $tool) {
            foreach ($tool['features'] ?? [] as $feature => $label) {
                $keys[self::toolKey($tool['slug'], $feature)] = [
                    'group' => 'Fitur: '.$tool['name'],
                    'label' => $label,
                    'type' => self::BOOL,
                    'default' => false,
                    'feature' => true,
                ];
            }
        }

        return $keys;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(string $prefix = ''): array
    {
        $rules = [];

        foreach ($this->all() as $key => $entry) {
            $rules[$prefix.$key] = match ($entry['type']) {
                self::BOOL => ['required', 'boolean'],
                self::SELECT => ['required', 'string', Rule::in(array_keys($entry['options']))],
                default => [($entry['nullable'] ?? false) ? 'nullable' : 'required', 'integer', 'min:'.$entry['min'], 'max:'.$entry['max']],
            };
        }

        return $rules;
    }

    /**
     * Validated input → stored JSON values; only registry keys are returned.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function cast(array $values): array
    {
        $cast = [];

        foreach ($this->all() as $key => $entry) {
            $value = $values[$key] ?? null;

            $cast[$key] = match ($entry['type']) {
                self::BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                self::SELECT => (string) $value,
                default => $value === null || $value === '' ? null : (int) $value,
            };
        }

        return $cast;
    }

    /**
     * Stored limits with registry defaults for missing keys (form state).
     *
     * @param  array<string, mixed>  $limits
     * @return array<string, mixed>
     */
    public function fill(array $limits): array
    {
        $values = [];

        foreach ($this->all() as $key => $entry) {
            $values[$key] = array_key_exists($key, $limits) ? $limits[$key] : $entry['default'];
        }

        return $values;
    }
}
