<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_only_invited_emails_can_register_when_a_list_is_set(): void
    {
        config(['stagepilot.invited_emails' => ['ami@example.com']]);
        $form = ['name' => 'Inconnu', 'password' => 'password', 'password_confirmation' => 'password'];

        $this->post(route('register.store'), $form + ['email' => 'inconnu@example.com'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('register.store'), $form + ['email' => 'Ami@Example.com'])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasVerifiedEmail(), 'an invited address needs no confirmation email');
    }
}
