<?php

namespace App\Actions\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait SwapsSortOrder
{
    /**
     * Swaps the "sort" of $item with its neighbour in $ordered (already sorted, locked rows).
     *
     * @param  Collection<int, Model>  $ordered
     * @return bool false when $item is already at the edge
     */
    protected function swapWithNeighbour(Collection $ordered, Model $item, bool $up): bool
    {
        $ordered = $ordered->values();
        $index = $ordered->search(fn (Model $m) => $m->is($item));
        $neighbour = $index === false ? null : $ordered->get($up ? $index - 1 : $index + 1);

        if ($neighbour === null) {
            return false;
        }

        [$a, $b] = [$item->sort, $neighbour->sort];
        // Equal sort values would make the swap a no-op.
        $item->update(['sort' => $a === $b ? $b + ($up ? -1 : 1) : $b]);
        $neighbour->update(['sort' => $a]);

        return true;
    }
}
