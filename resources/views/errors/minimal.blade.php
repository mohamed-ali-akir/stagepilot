{{-- Replaces Laravel's default error layout (404, 403, 419, 429, 500, 503...): same look as the app, in French. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>@yield('title') - {{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #18181b; color: #fafafa; }
            main { max-width: 28rem; padding: 2rem 1rem; text-align: center; }
            .code { font-size: 4rem; font-weight: 600; letter-spacing: -0.02em; margin: 0; }
            .message { font-size: 1.125rem; color: #d4d4d8; margin: 0.5rem 0 1.5rem; }
            .hint { font-size: 0.875rem; color: #a1a1aa; margin-bottom: 1.5rem; }
            a { display: inline-block; padding: 0.6rem 1.1rem; border-radius: 0.5rem; background: #fafafa; color: #18181b; text-decoration: none; font-weight: 500; }
            a:focus-visible { outline: 2px solid #60a5fa; outline-offset: 2px; }
        </style>
    </head>
    <body>
        <main>
            <p class="code">@yield('code')</p>
            <p class="message">@yield('message')</p>
            @if (trim($__env->yieldContent('code')) === '419')
                <p class="hint">La page est restée ouverte trop longtemps : recharge-la puis recommence.</p>
            @elseif (trim($__env->yieldContent('code')) === '500')
                <p class="hint">L'erreur a été enregistrée. Réessaie dans un instant.</p>
            @endif
            <a href="{{ url('/') }}">Retour à l'accueil</a>
        </main>
    </body>
</html>
