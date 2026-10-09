<?php

namespace App\Services\Hooks;

use Anthropic\Client;
use App\Models\Company;
use App\Models\User;

/**
 * Paid option: asks Claude (Anthropic API) for a hook, as structured output (see HookProposal).
 * The answer is then checked by HookVerifier: the AI is never trusted blindly.
 */
class ClaudeHookGenerator implements HookGenerator
{
    public function __construct(private ?string $apiKey, private string $model) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        if (blank($this->apiKey)) {
            return 'clé API Anthropic absente (ANTHROPIC_API_KEY dans .env)';
        }
        if (trim($siteText) === '') {
            return 'texte du site vide';
        }

        try {
            $client = new Client(apiKey: $this->apiKey);
            $message = $client->messages->create(
                model: $this->model,
                maxTokens: 16000,
                system: HookPrompt::system($user),
                messages: [['role' => 'user', 'content' => HookPrompt::user($company, $siteText)]],
                outputConfig: ['format' => HookProposal::class, 'effort' => 'low'],
            );
        } catch (\Throwable $e) {
            return 'erreur API Claude : '.class_basename($e).' '.mb_substr($e->getMessage(), 0, 150);
        }

        if ($message->stopReason === 'refusal') {
            return 'Claude a refusé de répondre';
        }

        $proposal = $message->parsedOutput();

        return $proposal instanceof HookProposal ? $proposal : 'réponse de Claude illisible';
    }
}
