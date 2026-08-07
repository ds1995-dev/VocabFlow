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
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 他の外部キーと違い cascadeOnDelete にしない。
            // 単語を消したときに過去の学習記録まで消えるとストリークが遡って壊れるため。
            // 将来のクイズ開始など単語に紐づかない記録も想定して nullable にしている。
            $table->foreignId('word_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            // 学習日。config('study.timezone') に換算済みの日付を保存する。
            // created_at（UTC）と冗長だが、集計からタイムゾーン変換を追い出すための非正規化。
            $table->date('studied_on');
            $table->timestamps();

            // ストリーク集計は常に user_id で絞って studied_on を見るため複合インデックスを張る。
            $table->index(['user_id', 'studied_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_activities');
    }
};
