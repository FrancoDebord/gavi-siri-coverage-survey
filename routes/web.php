<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';


// Route::get('/', [RecruitmentController::class, 'home'])->name('home');
Route::get('/menages', [RecruitmentController::class, 'menages'])->name('menages');
Route::get('/meres', [RecruitmentController::class, 'meres'])->name('meres');
Route::get('/enfants', [RecruitmentController::class, 'enfants'])->name('enfants');
Route::get('/errors', [DashboardController::class, 'errors'])->name('data.errors'); // page erreurs (à implémenter)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');

Route::get('/details/menages', [DashboardController::class, 'detailsMenages'])->name('details.menages');
Route::get('/details/enfants', [DashboardController::class, 'detailsEnfants'])->name('details.enfants');
Route::get('/details/meres', [DashboardController::class, 'detailsMeres'])->name('details.meres');
