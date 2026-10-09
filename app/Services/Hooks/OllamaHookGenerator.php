<?php

namespace App\Services\Hooks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Free option: an AI running on the student's own computer with Ollama (https://ollama.com).
 * No account, no cost, nothing leaves the PC. Needs the PC to be on.
 */
class OllamaHookGenerator implements HookGenerator
{
    public function __construct(private string $url, private string $model) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        if (trim($siteText) === '') {
            return 'texte du site vide';
        }

        try {
            $response = Http::timeout(300)->post(rtrim($this->url, '/').'/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'format' => HookPrompt::jsonSchema(),
                'options' => ['temperature' => 0.3],
                'messages' => [
                    ['role' => 'system', 'content' => HookPrompt::system($user)],
                    ['role' => 'user', 'content' => HookPrompt::user($company, $siteText)],
                ],
            ]);
        } catch (\Throwable $e) {
            return 'Ollama injoignable ('.$this->url.') : est-il lancé ?';
        }

        if (! $response->successful()) {
            return 'erreur Ollama '.$response->status().' : '.mb_substr((string) $response->json('error'), 0, 150);
        }

        return HookPrompt::parse((string) $response->json('message.content'));
    }
}
