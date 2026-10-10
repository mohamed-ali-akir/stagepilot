<?php

it('shows the app\'s own error page, in French, with a way back', function () {
    $this->get('/cette-page-n-existe-pas')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('Retour à l\'accueil');
});
