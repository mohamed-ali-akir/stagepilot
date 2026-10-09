<?php

namespace App\Services\Hooks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Free option: GroqCloud's free plan (no bank card), which runs large open models such as gpt-oss-120b.
 * Limits are per day and per minute, enough for a few students preparing ~10 hooks a day each.
 */
class GroqHookGenerator implements HookGenerator
{
    public function __construct(private ?string $apiKey, private string $model) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        if (blank($this->apiKey)) {
            return 'clé API Groq absente (GROQ_API_KEY dans .env)';
        }
        if (trim($siteText) === '') {
            return 'texte du site vide';
        }

        try {
            // the free plan allows ~8,000 tokens a minute (about 2 hooks): wait as long as Groq asks, then retry
            $response = Http::withToken($this->apiKey)->timeout(120)
                ->retry(3, fn (int $attempt, \Throwable $e) => 1000 * min(65, max(1, (int) ($e->response?->header('retry-after') ?: 30))),
                    fn (\Throwable $e) => $e instanceof RequestException && $e->response->status() === 429, throw: false)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
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
            return 'API Groq injoignable : '.mb_substr($e->getMessage(), 0, 120);
        }

        if ($response->status() === 429) {
            return 'limite gratuite de Groq atteinte : réessai à la prochaine préparation';
        }
        if (! $response->successful()) {
            return 'erreur API Groq '.$response->status().' : '.mb_substr((string) $response->json('error.message', $response->body()), 0, 150);
        }

        return HookPrompt::parse((string) $response->json('choices.0.message.content'));
    }
}
