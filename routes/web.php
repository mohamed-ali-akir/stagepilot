<?php

use App\Http\Controllers\StartDemoController;
use App\Livewire\Applications;
use App\Livewire\Dashboard;
use App\Livewire\Onboarding;
use App\Livewire\Review;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// "Essayer la démo": shared demo account, fictional data, no Gmail (limited to 10 per minute and per visitor)
Route::post('demo', StartDemoController::class)->middleware(['guest', 'throttle:10,1'])->name('demo');

Route::middleware(['auth', 'verified'])->group(function () {
    // The questionnaire (first time) and "my application profile" (later edits) are the same page.
    Route::livewire('questionnaire', Onboarding::class)->name('onboarding');

    Route::middleware('onboarded')->group(function () {
        Route::livewire('dashboard', Dashboard::class)->name('dashboard');
        Route::livewire('a-valider', Review::class)->name('review');
        Route::livewire('candidatures', Applications::class)->name('applications');
        // Vue 3 pages, fed by the REST API (routes/api.php)
        Route::view('suivi', 'board')->name('board');
        Route::view('statistiques', 'stats')->name('stats');
    });
});

require __DIR__.'/settings.php';
