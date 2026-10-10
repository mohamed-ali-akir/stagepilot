<?php

it('serves the Vue pages to a logged-in student, with the element Vue mounts into', function (string $route, string $mount) {
    $this->actingAs(student())
        ->get(route($route))
        ->assertOk()
        ->assertSee('id="'.$mount.'"', false);
})->with([
    ['board', 'kanban-app'],
    ['stats', 'stats-app'],
]);

it('keeps the Vue pages behind the login', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['board', 'stats']);

it('shows the Vue pages in the menu', function () {
    $this->actingAs(student())
        ->get(route('dashboard'))
        ->assertSee('Suivi des réponses')
        ->assertSee('Statistiques');
});
