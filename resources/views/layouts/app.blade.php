<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (auth()->user()?->is_demo)
            <flux:callout icon="eye" color="amber" class="mb-6" data-test="demo-banner">
                <flux:callout.heading>Mode démo</flux:callout.heading>
                <flux:callout.text>
                    Entreprises et candidatures fictives. Aucun mail n'est envoyé, rien n'est recherché, et tout est remis à zéro chaque nuit.
                    {{ session('demo_blocked') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
