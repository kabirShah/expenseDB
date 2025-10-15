<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExpenseController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::get('/expenses', [ExpenseController::class, 'index'])->name('index'); // List all expenses
Route::post('/expenses', [ExpenseController::class, 'store'])->name('store'); // Add a new expense
Route::get('/expenses/{id}', [ExpenseController::class, 'show'])->name('show'); // Get a single expense
Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('update'); // Update expense
Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('destory'); // Delete expense