<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('word_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // learning_activities の word_id とは逆に cascadeOnDelete にする。
            // あちらは「いつ学習したか」の履歴台帳なので単語が消えても残す必要があるが、
            // こちらは「その単語の現在の復習状態」なので単語が消えれば意味を失う。
            // 孤児を残すと復習件数が実在しない単語を数えてしまう。
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            // Leitner の箱番号。config('quiz.intervals') の添字がそのまま入る。
            $table->unsignedTinyInteger('box')->default(0);
            // 次に出題してよくなる学習日。studied_on と同じく学習日タイムゾーンに換算済み。
            $table->date('due_on');
            $table->date('last_reviewed_on')->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->timestamps();

            // 1単語につき復習状態はちょうど1つ、が不変条件。
            $table->unique('word_id');
            // 「このユーザーで期日が来ている単語」を引くのがこのテーブル唯一の重い読み取り。
            $table->index(['user_id', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_reviews');
    }
};
