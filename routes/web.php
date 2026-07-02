<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/login', 'auth/Login')->name('login');

Route::inertia('/admin/dashboard', 'admin/Dashboard')->name('admin.dashboard');
