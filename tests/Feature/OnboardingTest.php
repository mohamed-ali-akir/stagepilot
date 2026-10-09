<?php

use App\Livewire\Onboarding;
use App\Models\Program;
use App\Models\User;
use App\Services\Geocoder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');
    fakeMailbox();
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => [[
        'geometry' => ['coordinates' => [4.8539, 46.7806]],
        'properties' => ['city' => 'Chalon-sur-Saône', 'label' => '1 Place de l\'Hôtel de Ville 71100 Chalon-sur-Saône'],
    ]]])]);
    $this->user = User::factory()->create();
});

function fillQuestionnaire($component)
{
    return $component
        ->set('program_id', Program::where('name', 'BTS SIO option SLAM')->value('id'))
        ->set('school', 'Lycée Mathias')
        ->set('internship_start', '2027-01-04')
        ->set('internship_end', '2027-02-12')
        ->call('next')
        ->set('address', '1 place de l\'Hôtel de Ville, Chalon')
        ->set('radius_km', 35)
        ->call('next')
        ->set('experiences.0.title', 'Stage développeur web')
        ->set('experiences.0.description', 'Migration d\'une application métier PHP vers Laravel, module par module.')
        ->set('experiences.0.role', 'team')
        ->call('next')
        ->set('skills.0.name', 'Laravel')
        ->set('skills.0.level', 'good')
        ->call('next')
        ->set('phone', '06 01 02 03 04')
        ->set('cv', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
        ->set('gmail_app_password', 'abcd efgh ijkl mnop');
}

it('creates the profile with sending paused', function () {
    fillQuestionnaire(Livewire::actingAs($this->user)->test(Onboarding::class))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = $this->user->fresh(['profile', 'experiences', 'skills']);
    expect($user->profile->city)->toBe('Chalon-sur-Saône')
        ->and($user->profile->latitude)->toBe(46.7806)
        ->and($user->profile->paused)->toBeTrue()
        ->and($user->experiences)->toHaveCount(1)
        ->and($user->experiences[0]->role->value)->toBe('team')
        ->and($user->skills->pluck('name')->all())->toBe(['Laravel'])
        ->and($user->gmail_app_password)->toBe('abcdefghijklmnop');
    Storage::disk('local')->assertExists($user->profile->cv_path);
});

it('stores the Gmail app password encrypted', function () {
    fillQuestionnaire(Livewire::actingAs($this->user)->test(Onboarding::class))->call('save');

    $raw = DB::table('users')->where('id', $this->user->id)->value('gmail_app_password');
    expect($raw)->not->toContain('abcdefghijklmnop');
});

it('validates each step before moving on', function () {
    Livewire::actingAs($this->user)->test(Onboarding::class)
        ->set('internship_start', '2027-02-12')
        ->set('internship_end', '2027-01-04')
        ->call('next')
        ->assertHasErrors(['program_id', 'school', 'internship_end'])
        ->assertSet('step', 1);
});

it('refuses an address that cannot be found', function () {
    $this->mock(Geocoder::class, fn ($mock) => $mock->shouldReceive('locate')->andReturn(null));

    Livewire::actingAs($this->user)->test(Onboarding::class)
        ->set('step', 2)
        ->set('address', 'nulle part')
        ->call('next')
        ->assertHasErrors('address')
        ->assertSet('step', 2);
});

it('tests the Gmail app password', function () {
    Livewire::actingAs($this->user)->test(Onboarding::class)
        ->set('gmail_app_password', 'abcdefghijklmnop')->call('testGmail')->assertSet('gmailOk', true)
        ->set('gmail_app_password', 'zzzzzzzzzzzzzzzz')->call('testGmail')->assertSet('gmailOk', false);
});

it('reloads the profile for editing without asking the CV or password again', function () {
    $user = student(['cv_path' => 'cvs/cv-1.pdf']);

    Livewire::actingAs($user)->test(Onboarding::class)
        ->assertSet('school', $user->profile->school)
        ->assertSet('experiences.0.title', 'Stage développeur web')
        ->set('school', 'Nouveau lycée')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->profile->refresh()->school)->toBe('Nouveau lycée')
        ->and($user->fresh()->gmail_app_password)->toBe('abcdefghijklmnop');
});
