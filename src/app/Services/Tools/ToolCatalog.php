<?php

namespace App\Services\Tools;

use App\Enums\ToolStatus;
use App\Models\ToolSetting;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use Illuminate\Support\Facades\Cache;

// Tool catalog from config, with status overridden by admin (tool_settings). Feature access follows the plan matrix.
class ToolCatalog
{
    private const CACHE_KEY = 'tool_settings';

    public function __construct(protected PlanLimits $plans) {}

    /**
     * @return list<array<string, mixed>> each tool with resolved 'status' (ToolStatus value) and 'features' (key => label)
     */
    public function all(): array
    {
        $overrides = $this->overrides();

        return array_map(function (array $tool) use ($overrides) {
            $override = $overrides[$tool['slug']] ?? [];

            $tool['status'] = ($override['status'] ?? ToolStatus::tryFrom((string) ($tool['status'] ?? '')) ?? ToolStatus::Live)->value;
            $tool['features'] ??= [];

            return $tool;
        }, config('toolkits.tools', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        foreach ($this->all() as $tool) {
            if ($tool['slug'] === $slug) {
                return $tool;
            }
        }

        return null;
    }

    public function status(string $slug): ?ToolStatus
    {
        $tool = $this->find($slug);

        return $tool ? ToolStatus::from($tool['status']) : null;
    }

    // Unknown features stay locked so a typo never opens a gated feature.
    public function canUseFeature(string $slug, string $feature, Tier $tier): bool
    {
        if (! isset($this->find($slug)['features'][$feature])) {
            return false;
        }

        return (bool) ($this->plans->for($tier)[Entitlements::toolKey($slug, $feature)] ?? false);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{status?: ToolStatus}>
     */
    private function overrides(): array
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => ToolSetting::query()->toBase()
            ->get(['slug', 'status'])
            ->mapWithKeys(fn ($row) => [$row->slug => ['status' => $row->status]])
            ->all());

        // Unknown status values (e.g. removed enum case) fall back to the config default.
        return array_map(fn (array $row) => array_filter([
            'status' => ToolStatus::tryFrom((string) $row['status']),
        ]), $rows);
    }
}
