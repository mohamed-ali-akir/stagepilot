<?php

namespace App\Providers;

use App\Services\Hooks\ClaudeHookGenerator;
use App\Services\Hooks\GroqHookGenerator;
use App\Services\Hooks\HookGenerator;
use App\Services\Hooks\MistralHookGenerator;
use App\Services\Hooks\OllamaHookGenerator;
use App\Services\Hooks\TemplateHookGenerator;
use App\Services\Mail\GmailMailbox;
use App\Services\Mail\Mailbox;
use App\Services\Web\PageFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Real Gmail in the app; tests swap it for a fake.
        $this->app->bind(Mailbox::class, GmailMailbox::class);

        // Strategy pattern: the hook writer is chosen in .env (HOOK_PROVIDER), free by default.
        $this->app->bind(HookGenerator::class, fn () => match (config('stagepilot.hook_provider')) {
            'ollama' => new OllamaHookGenerator(config('stagepilot.ollama.url'), config('stagepilot.ollama.model')),
            'groq' => new GroqHookGenerator(config('stagepilot.groq.api_key'), config('stagepilot.groq.model')),
            'mistral' => new MistralHookGenerator(config('stagepilot.mistral.api_key'), config('stagepilot.mistral.model')),
            'claude' => new ClaudeHookGenerator(config('stagepilot.anthropic.api_key'), config('stagepilot.anthropic.model')),
            default => new TemplateHookGenerator,
        });
        // One page cache per request / job: the same page is often needed twice.
        $this->app->scoped(PageFetcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // REST API: 60 requests a minute per student (or per IP address before logging in), then HTTP 429
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
