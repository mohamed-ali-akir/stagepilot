<?php

namespace App\Services\Hooks;

use App\Models\Company;
use App\Models\User;

/**
 * Writes a hook for one company. Implemented with Claude; replaced by a fake in tests.
 */
interface HookGenerator
{
    /**
     * @return HookProposal|string the proposal, or an error message
     */
    public function propose(User $user, Company $company, string $siteText): HookProposal|string;
}
