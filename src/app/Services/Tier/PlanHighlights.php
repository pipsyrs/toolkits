<?php

namespace App\Services\Tier;

use Illuminate\Support\Number;

// Pricing card bullets derived from a plan's limits, so the card always matches what the plan enforces.
class PlanHighlights
{
    private const MINUTES_PER_DAY = 1440;

    public function __construct(protected Entitlements $entitlements) {}

    /**
     * @param  array<string, mixed>  $limits
     * @return list<string>
     */
    public function for(array $limits): array
    {
        $limits = $this->entitlements->fill($limits);
        $count = fn (mixed $value) => Number::format((int) $value, locale: 'id');

        $lines = [
            'File hingga '.$count(Entitlements::uploadLimit($limits, 'max_file_mb')).' MB',
            ($limits['max_files_per_op'] === null ? 'Hingga ' : '').$count(Entitlements::uploadLimit($limits, 'max_files_per_op')).' file per proses',
            $this->daily($limits['file_ops_per_day'], 'proses file', $count),
            $this->daily($limits['links_per_day'], 'link pendek', $count),
            'Hasil disimpan '.$this->duration(Entitlements::retentionMinutes($limits)),
            $limits['link_custom_expiry'] ? 'Atur kedaluwarsa link' : null,
            $limits['queue'] === 'pro' ? 'Antrean proses prioritas' : null,
            $limits['ads'] ? null : 'Bebas iklan',
        ];

        foreach ($this->entitlements->all() as $key => $entry) {
            if (str_starts_with($key, 'tool_') && $limits[$key]) {
                $lines[] = $entry['label'];
            }
        }

        return array_values(array_filter($lines));
    }

    private function daily(mixed $value, string $noun, callable $count): ?string
    {
        return match (true) {
            $value === null => ucfirst($noun).' tanpa batas harian',
            (int) $value > 0 => $count($value).' '.$noun.' per hari',
            default => null,
        };
    }

    private function duration(?int $minutes): string
    {
        return match (true) {
            $minutes === null => 'permanen',
            $minutes < self::MINUTES_PER_DAY => $minutes >= 60 && $minutes % 60 === 0 ? intdiv($minutes, 60).' jam' : $minutes.' menit',
            default => intdiv($minutes, self::MINUTES_PER_DAY).' hari',
        };
    }
}
