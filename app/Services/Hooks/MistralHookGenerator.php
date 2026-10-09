<?php

namespace App\Services\Hooks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Mistral AI's API (French company). Its free plan no longer includes API keys (checked October 2026):
 * pay-as-you-go needed.
 */
class MistralHookGenerator implements HookGenerator
{
    public function __construct(private ?string $apiKey, private string $model) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        if (blank($this->apiKey)) {
            return 'clé API Mistral absente (MISTRAL_API_KEY dans .env)';
        }
        if (trim($siteText) === '') {
            return 'texte du site vide';
        }

        try {
            $response = Http::withToken($this->apiKey)->timeout(120)->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => $this->model,
                'temperature' => 0.3,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'hook_proposal', 'schema' => HookPrompt::jsonSchema(), 'strict' => true],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => HookPrompt::system($user)],
                    ['role' => 'user', 'content' => HookPrompt::user($company, $siteText)],
                ],
            ]);
        } catch (\Throwable $e) {
            return 'API Mistral injoignable : '.mb_substr($e->getMessage(), 0, 120);
        }

        if ($response->status() === 429) {
            return 'limite gratuite de Mistral atteinte : réessai à la prochaine préparation';
        }
        if (! $response->successful()) {
            return 'erreur API Mistral '.$response->status().' : '.mb_substr((string) $response->json('message', $response->body()), 0, 150);
        }

        return HookPrompt::parse((string) $response->json('choices.0.message.content'));
    }
}
