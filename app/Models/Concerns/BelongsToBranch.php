<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());
    }
}
