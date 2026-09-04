<?php

use App\Http\Controllers\UnsubscribeAlertsController;
use App\Livewire\Account;
use App\Livewire\Admin\Subscribers;
use App\Livewire\AlertPreferencesForm;
use App\Livewire\Catalog;
use App\Livewire\Dashboard;
use App\Livewire\ForgotPassword;
use App\Livewire\Login;
use App\Livewire\Register;
use App\Livewire\ResetPassword;
use Illuminate\Support\Facades\Route;

Route::get('/', Catalog::class)->name('catalog');
Route::get('/register', Register::class)->name('register');
Route::get('/login', Login::class)->name('login');
Route::get('/logout', [Login::class, 'logout'])->name('logout');
Route::get('/esqueci-senha', ForgotPassword::class)->name('password.request');
Route::get('/redefinir-senha/{token}', ResetPassword::class)->name('password.reset');
Route::get('/alertas/unsubscribe/{user}', UnsubscribeAlertsController::class)
    ->name('alertas.unsubscribe');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/conta', Account::class)->name('conta');
    Route::get('/alertas', AlertPreferencesForm::class)->name('alertas');
});

Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->group(function () {
    Route::get('/assinantes', Subscribers::class)->name('admin.assinantes');
});
