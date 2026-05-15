<?php

use App\Http\Controllers\AccessTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AccessTokenController::class, 'show'])->name('login');
Route::post('/login', [AccessTokenController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/logout', [AccessTokenController::class, 'destroy'])->name('logout');

Route::middleware('token.auth')->group(function (): void {
    Route::livewire('/', 'dashboard')->name('dashboard');
    Route::livewire('/clientes', 'clients-page')->name('clients');
    Route::livewire('/projetos', 'projects-page')->name('projects');
    Route::livewire('/categorias', 'categories-page')->name('categories');
    Route::redirect('/registros', '/tarefas');
    Route::livewire('/tarefas', 'work-logs-page')->name('work-logs');
    Route::livewire('/relatorios', 'reports-page')->name('reports');
});
