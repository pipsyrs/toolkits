<?php

namespace App\Services\Tools;

use App\Enums\ToolStatus;
use App\Models\ToolCategory;
use App\Models\ToolSetting;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// Tool catalog from config, with status and category overridden by admin (tool_settings). Feature access follows the plan matrix.
class ToolCatalog
{
    private const CACHE_KEY = 'tool_settings';

    private const CATEGORIES_CACHE_KEY = 'tool_categories';

    public function __construct(protected PlanLimits $plans) {}

    /**
     * @return list<array<string, mixed>> each tool with resolved 'status' (ToolStatus value), 'category', 'category_name' and 'features' (key => label)
     */
    public function all(): array
    {
        $overrides = $this->overrides();
        $categories = $this->categories();

        return array_map(function (array $tool) use ($overrides, $categories) {
            $override = $overrides[$tool['slug']] ?? [];

            $tool['status'] = ($override['status'] ?? ToolStatus::tryFrom((string) ($tool['status'] ?? '')) ?? ToolStatus::Live)->value;
            $tool['category'] = $override['category'] ?? $tool['category'] ?? null;
            $tool['category_name'] = $tool['category'] === null ? '-' : ($categories[$tool['category']] ?? Str::headline($tool['category']));
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

    /**
     * @return array<string, string> slug => name, in admin order
     */
    public function categories(): array
    {
        return Cache::rememberForever(self::CATEGORIES_CACHE_KEY, fn () => ToolCategory::query()->toBase()
            ->orderBy('sort')->orderBy('id')
            ->pluck('name', 'slug')
            ->all());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CATEGORIES_CACHE_KEY);
    }

    /**
     * @return array<string, array{status?: ToolStatus, category?: string}>
     */
    private function overrides(): array
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => ToolSetting::query()->toBase()
            ->get(['slug', 'status', 'category'])
            ->mapWithKeys(fn ($row) => [$row->slug => ['status' => $row->status, 'category' => $row->category]])
            ->all());

        // Unknown status values (e.g. removed enum case) fall back to the config default.
        return array_map(fn (array $row) => array_filter([
            'status' => ToolStatus::tryFrom((string) $row['status']),
            'category' => $row['category'] ?? null,
        ]), $rows);
    }
}
