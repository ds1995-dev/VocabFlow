<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\WordController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

// 認証必須ルート（#58 で words/categories/user もここへ移動予定）
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::get('/words', [WordController::class, 'index']);
Route::post('/words', [WordController::class, 'store']);
Route::patch('/words/{word}', [WordController::class, 'update']);
Route::delete('/words/{word}', [WordController::class, 'destroy']);
Route::patch('/words/{word}/toggle-learned', [WordController::class, 'toggleLearned']);

Route::resource('categories', CategoryController::class);
