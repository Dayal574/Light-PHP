<?php

use App\Controllers\HomeController;
use App\Core\Route;

// Browser pages. The controller method renders one view and returns its HTML.
Route::get('/', [HomeController::class, 'index']);
Route::get('/about', [HomeController::class, 'about']);
Route::get('/documentation', [HomeController::class, 'documentation']);
