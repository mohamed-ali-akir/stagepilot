<?php

namespace App\Livewire;

use App\Enums\ExperienceRole;
use App\Enums\SkillLevel;
use App\Enums\ValidationMode;
use App\Models\Program;
use App\Models\Skill;
use App\Services\Geocoder;
use App\Services\Mail\Mailbox;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The 5-step questionnaire. Also used later to edit "my application profile".
 */
#[Title('Questionnaire')]
class Onboarding extends Component
{
    use WithFileUploads;

    public const STEPS = [1 => 'Formation', 2 => 'Localisation', 3 => 'Expériences', 4 => 'Compétences', 5 => 'Contact et envois'];

    public int $step = 1;

    // Step 1 - program and internship
    public ?int $program_id = null;

    public string $school = '';

    public string $internship_start = '';

    public string $internship_end = '';

    // Step 2 - location
    public string $address = '';

    public int $radius_km = 35;

    public bool $has_driving_licence = false;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $city = '';

    // Step 3 - experiences
    /** @var array<int, array{title: string, organization: ?string, description: string, role: string, in_production?: bool, technologies: ?string}> */
    public array $experiences = [];

    // Step 4 - skills
    /** @var array<int, array{name: string, level: string}> */
    public array $skills = [];

    // Step 5 - contact and sending
    public string $phone = '';

    public string $github_url = '';

    public string $portfolio_url = '';

    /** @var TemporaryUploadedFile|null */
    public $cv = null;

    public int $daily_quota = 20;

    public string $validation_mode = 'manual';

    public string $gmail_app_password = '';

    public ?bool $gmailOk = null;

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        if ($profile) {
            $this->fill($profile->only(['program_id', 'school', 'address', 'radius_km', 'has_driving_licence', 'latitude', 'longitude', 'daily_quota']));
            $this->city = (string) $profile->city;
            $this->internship_start = $profile->internship_start->toDateString();
            $this->internship_end = $profile->internship_end->toDateString();
            $this->phone = (string) $profile->phone;
            $this->github_url = (string) $profile->github_url;
            $this->portfolio_url = (string) $profile->portfolio_url;
            $this->validation_mode = $profile->validation_mode->value;
        }

        $this->experiences = $user->experiences->map(fn ($e) => [
            'title' => $e->title, 'organization' => (string) $e->organization, 'description' => $e->description,
            'role' => $e->role->value, 'in_production' => $e->in_production, 'technologies' => (string) $e->technologies,
        ])->all() ?: [$this->emptyExperience()];

        $this->skills = $user->skills->map(fn ($s) => ['name' => $s->name, 'level' => $s->pivot->level])->all()
            ?: [['name' => '', 'level' => SkillLevel::Good->value]];
    }

    /** @return Collection<int, Program> */
    #[Computed]
    public function programs()
    {
        return Program::orderBy('name')->get();
    }

    #[Computed]
    public function isEditing(): bool
    {
        return Auth::user()->hasCompletedOnboarding();
    }

    /** @return array<string, mixed> */
    public function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'program_id' => ['required', Rule::exists('programs', 'id')],
                'school' => ['required', 'string', 'max:150'],
                'internship_start' => ['required', 'date'],
                'internship_end' => ['required', 'date', 'after:internship_start'],
            ],
            2 => [
                'address' => ['required', 'string', 'max:200'],
                'radius_km' => ['required', 'integer', Rule::in([10, 20, 35, 50])],
                'has_driving_licence' => ['boolean'],
            ],
            3 => [
                'experiences' => ['required', 'array', 'min:1'],
                'experiences.*.title' => ['required', 'string', 'max:150'],
                'experiences.*.organization' => ['nullable', 'string', 'max:150'],
                'experiences.*.description' => ['required', 'string', 'min:20', 'max:1000'],
                'experiences.*.role' => ['required', Rule::enum(ExperienceRole::class)],
                'experiences.*.in_production' => ['boolean'],
                'experiences.*.technologies' => ['nullable', 'string', 'max:200'],
            ],
            4 => [
                'skills' => ['required', 'array', 'min:1'],
                'skills.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
                'skills.*.level' => ['required', Rule::enum(SkillLevel::class)],
            ],
            5 => [
                'phone' => ['required', 'string', 'max:20'],
                'github_url' => ['nullable', 'url', 'max:200'],
                'portfolio_url' => ['nullable', 'url', 'max:200'],
                'cv' => [$this->isEditing() && Auth::user()->profile?->cv_path ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:5120'],
                'daily_quota' => ['required', 'integer', 'min:1', 'max:'.config('stagepilot.max_daily_quota')],
                'validation_mode' => ['required', Rule::enum(ValidationMode::class)],
                'gmail_app_password' => ['nullable', 'string', 'regex:/^[a-zA-Z ]{16,19}$/'],
            ],
            default => throw new \InvalidArgumentException("Étape inconnue : {$step}"),
        };
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'gmail_app_password.regex' => 'Le mot de passe d\'application fait 16 lettres (les espaces sont acceptés).',
            'experiences.*.description.min' => 'Décris un peu plus ce que tu as fait (20 caractères minimum).',
            'cv.required' => 'Ajoute ton CV en PDF : il est joint à chaque candidature.',
        ];
    }

    public function next(Geocoder $geocoder): void
    {
        $this->validate($this->rulesForStep($this->step));

        if ($this->step === 2) {
            $location = $geocoder->locate($this->address);
            if ($location === null) {
                $this->addError('address', 'Adresse introuvable : vérifie-la (numéro, rue, ville).');

                return;
            }
            $this->latitude = $location['latitude'];
            $this->longitude = $location['longitude'];
            $this->city = $location['city'];
            $this->address = $location['label'];
        }

        $this->step = min(5, $this->step + 1);
    }

    public function previous(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        if ($this->isEditing() || $step < $this->step) {
            $this->step = max(1, min(5, $step));
        }
    }

    public function addExperience(): void
    {
        $this->experiences[] = $this->emptyExperience();
    }

    public function removeExperience(int $index): void
    {
        unset($this->experiences[$index]);
        $this->experiences = array_values($this->experiences) ?: [$this->emptyExperience()];
    }

    public function addSkill(): void
    {
        $this->skills[] = ['name' => '', 'level' => SkillLevel::Good->value];
    }

    public function removeSkill(int $index): void
    {
        unset($this->skills[$index]);
        $this->skills = array_values($this->skills) ?: [['name' => '', 'level' => SkillLevel::Good->value]];
    }

    public function testGmail(Mailbox $mailbox): void
    {
        if ($this->refuseInDemo()) {
            return;
        }
        $this->validateOnly('gmail_app_password', $this->rulesForStep(5));
        $this->gmailOk = filled($this->gmail_app_password)
            && $mailbox->canConnect(Auth::user()->email, str_replace(' ', '', $this->gmail_app_password));
    }

    public function save(): void
    {
        if ($this->refuseInDemo()) {
            return;
        }
        foreach (array_keys(self::STEPS) as $step) {
            try {
                $this->validate($this->rulesForStep($step));
            } catch (ValidationException $e) {
                $this->step = $step;
                throw $e;
            }
        }
        if ($this->latitude === null) {
            $this->step = 2;
            $this->addError('address', 'Valide ton adresse avec le bouton « Suivant ».');

            return;
        }

        $user = Auth::user();
        $firstTime = ! $user->hasCompletedOnboarding();

        DB::transaction(function () use ($user, $firstTime) {
            $data = [
                'program_id' => $this->program_id,
                'school' => $this->school,
                'internship_start' => $this->internship_start,
                'internship_end' => $this->internship_end,
                'address' => $this->address,
                'city' => $this->city,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'radius_km' => $this->radius_km,
                'has_driving_licence' => $this->has_driving_licence,
                'phone' => $this->phone,
                'github_url' => $this->github_url ?: null,
                'portfolio_url' => $this->portfolio_url ?: null,
                'daily_quota' => $this->daily_quota,
                'validation_mode' => $this->validation_mode,
            ];
            if ($this->cv) {
                $data['cv_path'] = $this->cv->storeAs('cvs', 'cv-'.$user->id.'.pdf', 'local');
            }
            if ($firstTime) {
                $data['paused'] = true; // nothing is sent until the student turns sending on
            }
            $user->profile()->updateOrCreate(['user_id' => $user->id], $data);

            $user->experiences()->delete();
            foreach ($this->experiences as $experience) {
                $user->experiences()->create([
                    ...$experience,
                    'organization' => $experience['organization'] ?: null,
                    'technologies' => $experience['technologies'] ?: null,
                    'in_production' => (bool) ($experience['in_production'] ?? false),
                ]);
            }

            $user->skills()->sync(collect($this->skills)->mapWithKeys(function ($skill) {
                $model = Skill::firstOrCreate(['name' => trim($skill['name'])]);

                return [$model->id => ['level' => $skill['level']]];
            })->all());

            if (filled($this->gmail_app_password)) {
                $user->forceFill(['gmail_app_password' => str_replace(' ', '', $this->gmail_app_password)])->save();
            }
        });

        session()->flash('status', $firstTime
            ? 'Profil créé ! Lance la recherche d\'entreprises ci-dessous. Les envois restent en pause tant que tu ne les actives pas.'
            : 'Profil mis à jour.');

        $this->redirectRoute('dashboard', navigate: true);
    }

    /** @return array{title: string, organization: string, description: string, role: string, in_production: bool, technologies: string} */
    private function emptyExperience(): array
    {
        return ['title' => '', 'organization' => '', 'description' => '', 'role' => ExperienceRole::Team->value, 'in_production' => false, 'technologies' => ''];
    }

    /** The demo profile can be looked at, never saved (and no Gmail password can be typed into it). */
    private function refuseInDemo(): bool
    {
        if (Auth::user()->is_demo) {
            Flux::toast(variant: 'warning', text: 'Dans la démo, le profil se consulte mais ne s\'enregistre pas.');
        }

        return Auth::user()->is_demo;
    }
}
