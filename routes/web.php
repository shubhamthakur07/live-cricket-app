<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\LiveScoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LiveScoreController::class, 'index'])->name('home');
Route::get('/live-scores', [LiveScoreController::class, 'index'])->name('live-scores.index');
Route::get('/live-scores/{matchId}', [LiveScoreController::class, 'show'])->name('live-scores.show');

Route::get('/dashboard', [MatchController::class, 'dashboard'])->name('dashboard');

// Public Instagram-style Profiles
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.user');

// Public CricHeroes Matches Arena (Index)
Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');

Route::middleware('auth')->group(function () {
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile-settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update.legacy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/users/{user}/follow', [ProfileController::class, 'toggleFollow'])->name('users.follow.toggle');

    Route::get('/matches/create', [MatchController::class, 'create'])->name('matches.create');
    Route::post('/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::get('/matches/{match}/edit', [MatchController::class, 'edit'])->name('matches.edit');
    Route::put('/matches/{match}', [MatchController::class, 'update'])->name('matches.update');
    Route::delete('/matches/{match}', [MatchController::class, 'destroy'])->name('matches.destroy');
    Route::post('/matches/{match}/quick-score', [MatchController::class, 'quickScore'])->name('matches.quick-score');
    Route::post('/matches/{match}/players/{player}/stats', [MatchController::class, 'updatePlayerStats'])->name('matches.players.update-stats');
    Route::post('/matches/{match}/scores', [MatchController::class, 'storeScore'])->name('matches.scores.store');
    Route::put('/matches/{match}/scores/{score}', [MatchController::class, 'updateScore'])->name('matches.scores.update');
    Route::delete('/matches/{match}/scores/{score}', [MatchController::class, 'destroyScore'])->name('matches.scores.destroy');
});

// Show individual match (MUST be after /matches/create so 'create' is not treated as a wildcard ID)
Route::get('/matches/{match}', [MatchController::class, 'show'])->name('matches.show');

require __DIR__.'/auth.php';
