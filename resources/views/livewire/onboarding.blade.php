<div class="mx-auto w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ $this->isEditing ? 'Mon profil de candidature' : 'Bienvenue sur StagePilot' }}</flux:heading>
        <flux:text class="mt-1">
            {{ $this->isEditing
                ? 'Ces informations servent à trouver les entreprises et à écrire tes accroches.'
                : '5 étapes, environ 5 minutes. Rien n\'est envoyé tant que tu n\'actives pas les envois.' }}
        </flux:text>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Étapes">
        @foreach (\App\Livewire\Onboarding::STEPS as $number => $label)
            <button type="button" wire:click="goTo({{ $number }})"
                @class([
                    'rounded-full px-3 py-1 text-sm border',
                    'bg-zinc-900 text-white border-zinc-900 dark:bg-white dark:text-zinc-900' => $step === $number,
                    'border-zinc-300 text-zinc-600 dark:border-zinc-600 dark:text-zinc-300' => $step !== $number,
                ])>
                {{ $number }}. {{ $label }}
            </button>
        @endforeach
    </nav>

    <flux:card class="space-y-6">
        @if ($step === 1)
            <flux:heading size="lg">Ta formation et ton stage</flux:heading>
            {{-- a real empty choice: without it the browser displays the first program while nothing is selected --}}
            <flux:select wire:model="program_id" label="Formation">
                <flux:select.option value="">Choisis ta formation</flux:select.option>
                @foreach ($this->programs as $program)
                    <flux:select.option value="{{ $program->id }}">{{ $program->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="school" label="Établissement" placeholder="Lycée Mathias, Chalon-sur-Saône" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="internship_start" type="date" label="Début du stage" />
                <flux:input wire:model="internship_end" type="date" label="Fin du stage" />
            </div>
        @elseif ($step === 2)
            <flux:heading size="lg">Où chercher ?</flux:heading>
            <flux:input wire:model="address" label="Ton adresse (ou ta ville)" placeholder="12 rue de la Paix, 71100 Chalon-sur-Saône"
                description="Elle sert uniquement à calculer la distance avec les entreprises." />
            @if ($latitude && $city)
                <flux:text class="text-sm">Adresse trouvée : {{ $address }}</flux:text>
            @endif
            <flux:radio.group wire:model="radius_km" label="Rayon de recherche" variant="segmented">
                <flux:radio value="10" label="10 km" />
                <flux:radio value="20" label="20 km" />
                <flux:radio value="35" label="35 km" />
                <flux:radio value="50" label="50 km" />
            </flux:radio.group>
            <flux:checkbox wire:model="has_driving_licence" label="J'ai le permis B et une voiture" />
        @elseif ($step === 3)
            <flux:heading size="lg">Tes expériences</flux:heading>
            <flux:text>Stages, jobs, projets. Sois exact : les accroches s'appuient dessus et ne doivent jamais exagérer.</flux:text>
            @foreach ($experiences as $i => $experience)
                <div wire:key="experience-{{ $i }}" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="experiences.{{ $i }}.title" label="Intitulé" placeholder="Stage développeur web" />
                        <flux:input wire:model="experiences.{{ $i }}.organization" label="Entreprise ou cadre (facultatif)" placeholder="Nex Informatique" />
                    </div>
                    <flux:textarea wire:model="experiences.{{ $i }}.description" label="Ce que tu as fait" rows="3"
                        placeholder="Migration d'une application métier PHP vers Laravel (Eloquent, Blade, gestion des droits)…" />
                    <flux:input wire:model="experiences.{{ $i }}.technologies" label="Technologies (facultatif)" placeholder="PHP, Laravel, MySQL" />
                    <div class="flex flex-wrap items-center gap-6">
                        <flux:radio.group wire:model="experiences.{{ $i }}.role" label="Tu as travaillé" variant="segmented">
                            @foreach (\App\Enums\ExperienceRole::cases() as $role)
                                <flux:radio value="{{ $role->value }}" label="{{ $role->label() }}" />
                            @endforeach
                        </flux:radio.group>
                        <flux:checkbox wire:model="experiences.{{ $i }}.in_production" label="C'est utilisé pour de vrai (en production)" />
                    </div>
                    @if (count($experiences) > 1)
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeExperience({{ $i }})">Retirer</flux:button>
                    @endif
                </div>
            @endforeach
            <flux:button icon="plus" wire:click="addExperience">Ajouter une expérience</flux:button>
        @elseif ($step === 4)
            <flux:heading size="lg">Tes compétences</flux:heading>
            @foreach ($skills as $i => $skill)
                <div wire:key="skill-{{ $i }}" class="flex items-end gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="skills.{{ $i }}.name" label="Compétence" placeholder="PHP, Laravel, SQL, anglais…" />
                    </div>
                    <flux:select wire:model="skills.{{ $i }}.level" label="Niveau" class="max-w-40">
                        @foreach (\App\Enums\SkillLevel::cases() as $level)
                            <flux:select.option value="{{ $level->value }}">{{ $level->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button variant="ghost" icon="trash" wire:click="removeSkill({{ $i }})" aria-label="Retirer" />
                </div>
            @endforeach
            <flux:button icon="plus" wire:click="addSkill">Ajouter une compétence</flux:button>
        @else
            <flux:heading size="lg">Contact et envois</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" label="Téléphone" placeholder="06 00 00 00 00" />
                <flux:input wire:model="daily_quota" type="number" min="1" max="20" label="Candidatures par jour (20 max)" />
                <flux:input wire:model="github_url" label="GitHub (facultatif)" placeholder="https://github.com/…" />
                <flux:input wire:model="portfolio_url" label="Portfolio (facultatif)" placeholder="https://…" />
            </div>

            <flux:field>
                <flux:label>CV en PDF (joint à chaque candidature)</flux:label>
                <input type="file" wire:model="cv" accept="application/pdf" class="text-sm" />
                <div wire:loading wire:target="cv"><flux:text class="text-sm">Envoi du fichier…</flux:text></div>
                @if ($this->isEditing && auth()->user()->profile?->cv_path && ! $cv)
                    <flux:text class="text-sm">Un CV est déjà enregistré. Choisis un fichier seulement pour le remplacer.</flux:text>
                @endif
                <flux:error name="cv" />
            </flux:field>

            <flux:radio.group wire:model="validation_mode" label="Validation des accroches">
                @foreach (\App\Enums\ValidationMode::cases() as $mode)
                    @php($unavailable = $mode === \App\Enums\ValidationMode::AutoWithVeto && ! $this->automaticValidationAvailable)
                    <flux:radio :value="$mode->value" :label="$mode->label()" :disabled="$unavailable"
                        :description="$unavailable ? 'Indisponible avec le moteur d\'accroches gratuit : chaque accroche doit être relue avant l\'envoi.' : null" />
                @endforeach
            </flux:radio.group>

            <flux:field>
                <flux:label>Mot de passe d'application Gmail ({{ auth()->user()->email }})</flux:label>
                <flux:description>
                    Pas ton vrai mot de passe : un code de 16 lettres créé sur
                    <flux:link href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</flux:link>
                    (validation en deux étapes obligatoire). Il est chiffré dans la base.
                    @if (auth()->user()->canSendEmails()) Déjà enregistré : laisse vide pour le garder. @endif
                </flux:description>
                <div class="flex gap-2">
                    <flux:input wire:model="gmail_app_password" type="password" viewable autocomplete="off" />
                    <flux:button wire:click="testGmail" wire:loading.attr="disabled">Tester</flux:button>
                </div>
                <flux:error name="gmail_app_password" />
                @if ($gmailOk === true)
                    <flux:text class="text-sm text-green-600">Connexion à Gmail réussie.</flux:text>
                @elseif ($gmailOk === false)
                    <flux:text class="text-sm text-red-600">Gmail refuse ce code (ou ton compte n'a pas la validation en deux étapes).</flux:text>
                @endif
            </flux:field>
        @endif

        <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button variant="ghost" wire:click="previous" :disabled="$step === 1">Précédent</flux:button>
            <div class="flex gap-2">
                @if ($this->isEditing && $step < 5)
                    <flux:button wire:click="save">Enregistrer</flux:button>
                @endif
                @if ($step < 5)
                    <flux:button variant="primary" wire:click="next" wire:loading.attr="disabled">Suivant</flux:button>
                @else
                    <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                        {{ $this->isEditing ? 'Enregistrer' : 'Terminer' }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>
</div>
