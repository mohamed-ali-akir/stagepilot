<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">À valider</flux:heading>
        <flux:text class="mt-1">
            Chaque accroche a été vérifiée : l'extrait cité a été retrouvé mot pour mot sur le site de l'entreprise.
            Relis-la, modifie-la si besoin, puis valide : elle partira au prochain envoi.
            @if ($this->waitingPreparation) {{ $this->waitingPreparation }} entreprise(s) attendent encore d'être préparées. @endif
        </flux:text>
    </div>

    @forelse ($this->applications as $application)
        <flux:card wire:key="review-{{ $application->id }}" class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <flux:heading size="lg">{{ $application->company->name }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ $application->company->city }} · {{ $application->distance_km }} km · priorité {{ $application->priority }}
                        @if ($application->company->website) · <flux:link href="{{ $application->company->website }}" target="_blank">site</flux:link> @endif
                    </flux:text>
                </div>
                <div class="text-right text-sm">
                    <div>{{ $application->company->email ?? 'pas d\'email' }}</div>
                    @if ($application->company->email_note)
                        <flux:badge size="sm" color="amber">{{ $application->company->email_note }}</flux:badge>
                    @endif
                </div>
            </div>

            @if ($application->hook_error && ! $application->hook)
                <flux:callout icon="exclamation-triangle" variant="warning" heading="Accroche non écrite automatiquement" :text="$application->hook_error.'. Tu peux l\'écrire toi-même.'" />
            @endif

            @if (array_key_exists($application->id, $drafts) || ! $application->hook)
                <flux:textarea wire:model="drafts.{{ $application->id }}" rows="3" placeholder="Une phrase : ce que fait l'entreprise + pourquoi elle t'intéresse." />
            @else
                <blockquote class="border-s-4 border-zinc-300 ps-4 text-zinc-800 dark:border-zinc-600 dark:text-zinc-100">{{ $application->hook }}</blockquote>
            @endif
            <flux:error name="drafts.{{ $application->id }}" />

            @if ($application->proof_excerpt)
                <flux:text class="text-sm">
                    Preuve : « {{ $application->proof_excerpt }} »
                    · <flux:link href="{{ $application->proof_url }}" target="_blank">{{ \Illuminate\Support\Str::limit($application->proof_url, 60) }}</flux:link>
                </flux:text>
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="check" wire:click="approve({{ $application->id }})">Valider</flux:button>
                @if ($application->hook && ! array_key_exists($application->id, $drafts))
                    <flux:button icon="pencil" wire:click="edit({{ $application->id }})">Modifier</flux:button>
                @endif
                <flux:button variant="ghost" icon="x-mark" wire:click="discard({{ $application->id }})">Écarter</flux:button>
            </div>
        </flux:card>
    @empty
        <flux:card>
            <flux:text>Rien à valider pour l'instant. Lance une préparation depuis le tableau de bord.</flux:text>
        </flux:card>
    @endforelse
</div>
