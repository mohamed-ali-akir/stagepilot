<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Bienvenue'])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-900 dark:text-white">
        <header class="mx-auto flex max-w-5xl items-center justify-between p-6">
            <x-app-logo />
            <nav class="flex gap-2">
                @auth
                    <flux:button variant="primary" :href="route('dashboard')">Tableau de bord</flux:button>
                @else
                    <flux:button variant="ghost" :href="route('login')">Se connecter</flux:button>
                    @if (Route::has('register'))
                        <flux:button variant="primary" :href="route('register')">Créer un compte</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="mx-auto max-w-5xl space-y-12 px-6 py-12">
            <section class="max-w-2xl space-y-4">
                <flux:heading size="xl" level="1" class="!text-4xl">Ta recherche de stage, en pilote automatique.</flux:heading>
                <flux:text class="text-lg">
                    Réponds à quelques questions : StagePilot trouve les entreprises autour de toi, prépare des candidatures
                    personnalisées et vérifiées, les envoie depuis ton Gmail et suit les réponses à ta place.
                </flux:text>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['Trouver', 'Les entreprises de ton secteur dans le rayon que tu choisis, avec leur site et leur email vérifiés.'],
                    ['Personnaliser', 'Une accroche par entreprise, appuyée sur une phrase réelle de son site et sur ton vrai parcours. Tu valides avant l\'envoi.'],
                    ['Suivre', '20 envois par jour au maximum, une relance, les réponses repérées et un brouillon de réponse prêt dans Gmail.'],
                ] as [$title, $text])
                    <flux:card>
                        <flux:heading size="lg">{{ $title }}</flux:heading>
                        <flux:text class="mt-2">{{ $text }}</flux:text>
                    </flux:card>
                @endforeach
            </section>

            <flux:text class="text-sm">Projet de BTS SIO option SLAM · gratuit · tes données restent à toi.</flux:text>
        </main>

        @fluxScripts
    </body>
</html>
