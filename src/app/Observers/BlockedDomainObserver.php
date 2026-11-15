<?php

namespace App\Observers;

use App\Models\BlockedDomain;
use App\Services\Links\BlockedDomainList;

class BlockedDomainObserver
{
    public function __construct(protected BlockedDomainList $list) {}

    public function saved(BlockedDomain $domain): void
    {
        $this->list->flush();
    }

    public function deleted(BlockedDomain $domain): void
    {
        $this->list->flush();
    }
}
