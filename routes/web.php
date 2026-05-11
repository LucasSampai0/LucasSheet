<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'dashboard')->name('dashboard');
Route::livewire('/clientes', 'clients-page')->name('clients');
Route::livewire('/projetos', 'projects-page')->name('projects');
Route::livewire('/categorias', 'categories-page')->name('categories');
Route::livewire('/registros', 'work-logs-page')->name('work-logs');
Route::livewire('/relatorios', 'reports-page')->name('reports');
