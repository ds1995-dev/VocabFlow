<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\WordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 公開ルート（未認証でもアクセス可能）
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

// 認証必須ルート
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/words', [WordController::class, 'index']);
    Route::post('/words', [WordController::class, 'store']);
    Route::patch('/words/{word}', [WordController::class, 'update']);
    Route::delete('/words/{word}', [WordController::class, 'destroy']);
    Route::patch('/words/{word}/toggle-learned', [WordController::class, 'toggleLearned']);

    Route::resource('categories', CategoryController::class);

    // 学習ストリーク（連続学習日数）
    Route::get('/streak', [StreakController::class, 'show']);

    // クイズ出題（4択の問題をサーバー側で組み立てて返す）
    Route::get('/quiz/questions', [QuizController::class, 'questions']);
    // クイズ回答の一括保存（正誤判定・box 更新・学習アクティビティの記録）
    Route::post('/quiz/answers', [QuizController::class, 'store']);
    // 今日の復習件数（ダッシュボードの導線に使う）
    Route::get('/quiz/summary', [QuizController::class, 'summary']);
});
