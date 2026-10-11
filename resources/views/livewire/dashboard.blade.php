<div class="space-y-6" wire:poll.30s>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Tableau de bord</flux:heading>
            <flux:text class="mt-1">{{ auth()->user()->profile->program->name }} · stage de {{ auth()->user()->profile->durationLabel() }} · {{ auth()->user()->profile->radius_km }} km autour de {{ auth()->user()->profile->city }}</flux:text>
            <flux:text class="text-sm">{{ $this->counts['to_prepare'] }} entreprise(s) trouvée(s) en attente de préparation (site, email, accroche).</flux:text>
        </div>
        <div class="flex flex-col items-end gap-1">
            <div class="flex flex-wrap gap-2">
                @if ($this->preparing)
                    <flux:button icon="arrow-path" variant="primary" disabled>Préparation en cours…</flux:button>
                @elseif ($this->remainingToday === 0)
                    <flux:button icon="sparkles" variant="primary" disabled>Limite du jour atteinte</flux:button>
                @else
                    <flux:dropdown position="bottom" align="end">
                        <flux:button icon="sparkles" icon:trailing="chevron-down" variant="primary">Préparer des candidatures</flux:button>
                        <flux:menu>
                            @foreach ($this->prepareChoices as $choice)
                                <flux:menu.item wire:click="prepare({{ $choice }})">{{ $choice }} accroche(s)</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endif
                <flux:button icon="envelope" wire:click="sendTest" wire:loading.attr="disabled">M'envoyer un exemple</flux:button>
            </div>
            <flux:text class="text-xs">
                {{ $this->remainingToday }} accroche(s) encore possible(s) aujourd'hui ({{ config('stagepilot.prepare_daily_limit') }} par jour au maximum)
            </flux:text>
        </div>
    </div>

    @if (session('status'))
        {{-- the heading attribute of flux:callout escapes twice ("d&#039;"): the sub-component escapes once --}}
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.heading>{{ session('status') }}</flux:callout.heading>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['À valider', $this->counts['review'], route('review'), 'amber'],
            ['Prêtes à partir', $this->counts['ready'], route('applications', ['status' => 'ready']), 'sky'],
            ['En attente de réponse', $this->counts['waiting'], route('applications', ['status' => 'sent']), 'blue'],
            ['Réponses à lire', $this->counts['replies'], route('applications', ['status' => 'reply_received']), 'violet'],
            ['Entretiens / positives', $this->counts['interviews'], route('applications', ['status' => 'interview']), 'green'],
        ] as [$label, $value, $href, $color])
            <a href="{{ $href }}" wire:navigate>
                <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <flux:text>{{ $label }}</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ $value }}</flux:heading>
                </flux:card>
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-1">
            <flux:heading size="lg">Envois automatiques</flux:heading>
            <flux:switch wire:model.live="sendingEnabled" label="Envoyer mes candidatures" description="Chaque jour ouvré à 9h, 20 au maximum, 1 à 3 min entre chaque." />
            @if ($this->blockedReason)
                <flux:callout icon="pause-circle" variant="warning" heading="Pas d'envoi pour l'instant" :text="ucfirst($this->blockedReason).'.'" />
            @else
                <flux:callout icon="play-circle" variant="success" heading="Les prochains envois partiront à 9h." />
            @endif
            <flux:separator />
            @if (auth()->user()->profile->internship_found_at)
                <flux:text>Stage trouvé le {{ auth()->user()->profile->internship_found_at->format('d/m/Y') }}. Bravo !</flux:text>
            @else
                <flux:modal.trigger name="found">
                    <flux:button icon="trophy" class="w-full">J'ai trouvé mon stage</flux:button>
                </flux:modal.trigger>
                <flux:modal name="found" class="max-w-md">
                    <flux:heading size="lg">Tu as trouvé ton stage ?</flux:heading>
                    <flux:text class="mt-2">Plus aucune candidature ni relance ne partira. Tu pourras toujours consulter ton suivi.</flux:text>
                    <div class="mt-6 flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">Annuler</flux:button></flux:modal.close>
                        <flux:button variant="primary" wire:click="internshipFound" x-on:click="$flux.modal('found').close()">Oui, tout arrêter</flux:button>
                    </div>
                </flux:modal>
            @endif
        </flux:card>

        <flux:card class="space-y-3 lg:col-span-2">
            <flux:heading size="lg">Ce que l'application a fait</flux:heading>
            @forelse ($this->activity as $log)
                <div wire:key="log-{{ $log->id }}" class="flex gap-3 text-sm">
                    <span class="w-28 shrink-0 text-zinc-500">{{ $log->created_at->format('d/m H:i') }}</span>
                    <span>
                        <span class="font-medium">{{ $log->label() }}</span>
                        @if ($log->jobApplication) · {{ $log->jobApplication->company->name }} @endif
                        @if ($log->details) <span class="text-zinc-500">· {{ \Illuminate\Support\Str::limit($log->details, 160) }}</span> @endif
                    </span>
                </div>
            @empty
                <flux:text>Rien pour l'instant. Clique sur « Préparer 5 candidatures » pour commencer.</flux:text>
            @endforelse
        </flux:card>
    </div>

    @if ($this->toCall->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:heading size="lg">À appeler (pas d'email trouvé)</flux:heading>
            <flux:text>« Bonjour, je suis étudiant en {{ auth()->user()->profile->program->name }}. Je cherche un stage de {{ auth()->user()->profile->durationLabel() }}. À qui pourrais-je envoyer ma candidature ? »</flux:text>
            @foreach ($this->toCall as $application)
                <div wire:key="call-{{ $application->id }}" class="flex flex-wrap gap-x-4 text-sm">
                    <span class="font-medium">{{ $application->company->name }}</span>
                    <span>{{ $application->company->city }}</span>
                    <span>{{ $application->company->phone }}</span>
                    @if ($application->company->website)
                        <flux:link href="{{ $application->company->website }}" target="_blank">site</flux:link>
                    @endif
                </div>
            @endforeach
        </flux:card>
    @endif
</div>
