<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Candidatures</flux:heading>
            <flux:text class="mt-1">Quand une entreprise te répond, lis sa réponse dans Gmail puis choisis le bon statut ici.</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Entreprise ou ville" />
            <flux:select wire:model.live="status" class="max-w-52">
                <flux:select.option value="">Tous les statuts</flux:select.option>
                <flux:select.option value="ready">Prêtes à partir</flux:select.option>
                <flux:select.option value="sent">En attente de réponse</flux:select.option>
                <flux:select.option value="reply_received">Réponse reçue</flux:select.option>
                <flux:select.option value="interview">Entretiens / positives</flux:select.option>
                <flux:select.option value="negative">Refus</flux:select.option>
                <flux:select.option value="pending_review">À valider</flux:select.option>
                <flux:select.option value="ignored">Écartées</flux:select.option>
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->applications">
        <flux:table.columns>
            <flux:table.column>Entreprise</flux:table.column>
            <flux:table.column>Ville</flux:table.column>
            <flux:table.column>Priorité</flux:table.column>
            <flux:table.column>Statut</flux:table.column>
            <flux:table.column>Envoyée le</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->applications as $application)
                <flux:table.row :key="$application->id">
                    <flux:table.cell>
                        <button type="button" class="text-start font-medium hover:underline" wire:click="show({{ $application->id }})">{{ $application->company->name }}</button>
                    </flux:table.cell>
                    <flux:table.cell>{{ $application->company->city }} ({{ $application->distance_km }} km)</flux:table.cell>
                    <flux:table.cell>{{ $application->priority }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$application->status->color()">{{ $application->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $application->sent_at?->format('d/m/Y') ?? '–' }}</flux:table.cell>
                    <flux:table.cell>
                        @if (in_array($application->status, [\App\Enums\ApplicationStatus::Sent, \App\Enums\ApplicationStatus::FollowedUp, \App\Enums\ApplicationStatus::ReplyReceived, \App\Enums\ApplicationStatus::Positive, \App\Enums\ApplicationStatus::Interview], true))
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">Statut</flux:button>
                                <flux:menu>
                                    @foreach (\App\Enums\ApplicationStatus::manualChoices() as $choice)
                                        <flux:menu.item wire:click="setStatus({{ $application->id }}, '{{ $choice->value }}')">{{ $choice->label() }}</flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="details" class="w-full max-w-2xl">
        @if ($this->selected)
            <div class="space-y-4">
                <flux:heading size="lg">{{ $this->selected->company->name }}</flux:heading>
                <flux:text class="text-sm">
                    {{ $this->selected->company->address }} · SIREN {{ $this->selected->company->siren }}<br>
                    {{ $this->selected->company->email ?? 'pas d\'email' }} · {{ $this->selected->company->phone ?? 'pas de téléphone' }}
                    @if ($this->selected->company->website) · <flux:link href="{{ $this->selected->company->website }}" target="_blank">site</flux:link> @endif
                </flux:text>
                @if ($this->selected->hook)
                    <div>
                        <flux:heading>Accroche</flux:heading>
                        <flux:text class="mt-1">{{ $this->selected->hook }}</flux:text>
                    </div>
                @endif
                @if ($this->selected->sentEmails->isNotEmpty() || $this->selected->replies->isNotEmpty())
                    <div>
                        <flux:heading>Historique</flux:heading>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($this->selected->sentEmails as $sent)
                                <li>{{ $sent->sent_at->format('d/m/Y H:i') }} · {{ $sent->type->value === 'application' ? 'Candidature envoyée' : 'Relance envoyée' }} à {{ $sent->recipient }}</li>
                            @endforeach
                            @foreach ($this->selected->replies as $reply)
                                <li>{{ $reply->received_at->format('d/m/Y H:i') }} · {{ $reply->type->label() }} de {{ $reply->sender }} : {{ $reply->subject }} @if ($reply->draft_created) (brouillon prêt dans Gmail) @endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($this->selected->notes)
                    <div>
                        <flux:heading>Notes</flux:heading>
                        <pre class="mt-1 whitespace-pre-wrap font-sans text-sm text-zinc-600 dark:text-zinc-300">{{ $this->selected->notes }}</pre>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
