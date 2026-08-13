<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WordReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 学習日を固定するため、日本時間 2026-08-12 12:00（UTC 03:00）を「今日」とする。
     */
    private function 日本時間の今日を2026年8月12日にする(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 03:00:00', 'UTC'));
    }

    /**
     * 指定した箱の復習状態を持つ単語を作る。
     */
    private function 復習状態のある単語を作る(User $user, int $box, string $dueOn): Word
    {
        $word = Word::factory()->for($user)->create();

        WordReview::factory()->for($user)->for($word)->create([
            'box' => $box,
            'due_on' => $dueOn,
        ]);

        return $word->fresh();
    }

    public function test_未出題の単語はfor_wordで箱0の復習状態が作られる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $user = User::factory()->create();
        $word = Word::factory()->for($user)->create();

        $review = WordReview::forWord($word);

        $this->assertSame(0, $review->box);
        $this->assertSame('2026-08-12', $review->due_on->toDateString());
        // 所有者はリクエストではなく単語から引き継ぐ
        $this->assertSame($user->id, $review->user_id);
        $this->assertDatabaseHas('word_reviews', [
            'word_id' => $word->id,
            'user_id' => $user->id,
            'box' => 0,
        ]);
    }

    public function test_for_wordを2回呼んでも復習状態は1件しか作られない(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $user = User::factory()->create();
        $word = Word::factory()->for($user)->create();

        $first = WordReview::forWord($word);
        $second = WordReview::forWord($word);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('word_reviews', 1);
    }

    public function test_正解で箱が1つ上がりdue_onが新しい箱の間隔分先になる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        // 箱 1 の単語に正解すると箱 2（間隔 7 日）へ進む
        $user = User::factory()->create();
        $word = $this->復習状態のある単語を作る($user, 1, '2026-08-12');

        $review = WordReview::forWord($word);
        $review->applyAnswer(true, '2026-08-12');

        $this->assertSame(2, $review->box);
        $this->assertSame('2026-08-19', $review->due_on->toDateString());
        $this->assertSame('2026-08-12', $review->last_reviewed_on->toDateString());
        $this->assertSame(1, $review->correct_count);
        $this->assertSame(0, $review->incorrect_count);
    }

    public function test_誤答で箱が0に戻りdue_onが翌日になる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        // 箱 4 まで進んでいても誤答すれば箱 0（間隔 1 日）に戻る
        $user = User::factory()->create();
        $word = $this->復習状態のある単語を作る($user, 4, '2026-08-12');

        $review = WordReview::forWord($word);
        $review->applyAnswer(false, '2026-08-12');

        $this->assertSame(0, $review->box);
        $this->assertSame('2026-08-13', $review->due_on->toDateString());
        $this->assertSame(0, $review->correct_count);
        $this->assertSame(1, $review->incorrect_count);
    }

    public function test_箱は最上位で頭打ちになる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        // 最上位の箱 5（間隔 60 日）でさらに正解しても 6 にはならない
        $user = User::factory()->create();
        $word = $this->復習状態のある単語を作る($user, WordReview::maxBox(), '2026-08-12');

        $review = WordReview::forWord($word);
        $review->applyAnswer(true, '2026-08-12');

        $this->assertSame(5, $review->box);
        $this->assertSame('2026-10-11', $review->due_on->toDateString());
    }

    public function test_正解数と誤答数が積み上がる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $user = User::factory()->create();
        $word = Word::factory()->for($user)->create();

        $review = WordReview::forWord($word);
        $review->applyAnswer(true, '2026-08-12');
        $review->applyAnswer(false, '2026-08-12');
        $review->applyAnswer(true, '2026-08-12');

        $this->assertSame(2, $review->correct_count);
        $this->assertSame(1, $review->incorrect_count);
    }

    public function test_箱ごとの間隔がconfigの並びと一致する(): void
    {
        $this->assertSame(1, WordReview::intervalFor(0));
        $this->assertSame(3, WordReview::intervalFor(1));
        $this->assertSame(7, WordReview::intervalFor(2));
        $this->assertSame(14, WordReview::intervalFor(3));
        $this->assertSame(30, WordReview::intervalFor(4));
        $this->assertSame(60, WordReview::intervalFor(5));
        // 範囲外を渡しても最上位の間隔に丸める
        $this->assertSame(60, WordReview::intervalFor(99));
        $this->assertSame(1, WordReview::intervalFor(-1));
    }

    public function test_期日が今日以前の単語がdue_countに数えられる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $user = User::factory()->create();
        // 期日超過・今日が期日・未来が期日 の3件
        $this->復習状態のある単語を作る($user, 1, '2026-08-10');
        $this->復習状態のある単語を作る($user, 1, '2026-08-12');
        $this->復習状態のある単語を作る($user, 1, '2026-08-13');

        $this->assertSame(2, WordReview::dueCountFor($user));
    }

    public function test_一度も出題していない単語がnew_countに数えられる(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $user = User::factory()->create();
        // 復習状態あり1件・未出題2件
        $this->復習状態のある単語を作る($user, 1, '2026-08-12');
        Word::factory()->count(2)->for($user)->create();

        $this->assertSame(2, WordReview::newCountFor($user));
    }

    public function test_他人の復習状態は件数に数えられない(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->復習状態のある単語を作る($other, 1, '2026-08-10');
        Word::factory()->for($other)->create();

        $this->assertSame(0, WordReview::dueCountFor($me));
        $this->assertSame(0, WordReview::newCountFor($me));
    }

    public function test_単語を削除すると復習状態も消える(): void
    {
        $this->日本時間の今日を2026年8月12日にする();

        // learning_activities と違い、復習状態は単語の現在の状態なので道連れに消す
        $user = User::factory()->create();
        $word = $this->復習状態のある単語を作る($user, 2, '2026-08-12');

        $word->delete();

        $this->assertDatabaseCount('word_reviews', 0);
    }
}
