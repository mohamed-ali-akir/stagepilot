<?php

use App\Livewire\Applications;
use App\Livewire\Dashboard;
use App\Livewire\Onboarding;
use App\Livewire\Review;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The questionnaire (first time) and "my application profile" (later edits) are the same page.
    Route::livewire('questionnaire', Onboarding::class)->name('onboarding');

    Route::middleware('onboarded')->group(function () {
        Route::livewire('dashboard', Dashboard::class)->name('dashboard');
        Route::livewire('a-valider', Review::class)->name('review');
        Route::livewire('candidatures', Applications::class)->name('applications');
    });
});

require __DIR__.'/settings.php';
