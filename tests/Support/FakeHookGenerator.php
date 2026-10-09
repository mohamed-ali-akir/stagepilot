<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\User;
use App\Services\Hooks\HookGenerator;
use App\Services\Hooks\HookProposal;

/**
 * Claude stand-in for tests: returns a prepared proposal (no API call, nothing to pay).
 */
class FakeHookGenerator implements HookGenerator
{
    public int $calls = 0;

    public function __construct(public HookProposal|string|null $answer = null) {}

    public static function proposal(string $hook, string $excerpt, string $url, bool $relevant = true, string $reason = ''): HookProposal
    {
        $proposal = new HookProposal;
        $proposal->relevant = $relevant;
        $proposal->reason = $reason;
        $proposal->hook = $hook;
        $proposal->excerpt = $excerpt;
        $proposal->url = $url;

        return $proposal;
    }

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        $this->calls++;

        return $this->answer ?? 'pas de réponse préparée';
    }
}
