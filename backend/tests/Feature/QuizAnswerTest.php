<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuizAnswerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 学習日を固定するため、日本時間 2026-08-15 12:00（UTC 03:00）を「今日」とする。
     */
    private function 日本時間の今日を2026年8月15日にする(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 03:00:00', 'UTC'));
    }

    /**
     * 単語を1件持つユーザーを作る。
     *
     * @return array{0: User, 1: Word}
     */
    private function 単語を持つユーザーを作る(): array
    {
        $user = User::factory()->create();
        $word = Word::factory()->for($user)->for(Category::factory()->for($user))->create();

        return [$user, $word];
    }

    /**
     * 指定した箱の復習状態を単語に持たせる。
     */
    private function 復習状態を作る(User $user, Word $word, int $box): WordReview
    {
        return WordReview::factory()->for($user)->for($word)->create([
            'box' => $box,
            'due_on' => '2026-08-15',
        ]);
    }

    /**
     * 回答を1件送る。
     */
    private function 回答を送る(User $user, array $answers)
    {
        return $this->actingAsUser($user)->postJson('/api/quiz/answers', ['answers' => $answers]);
    }

    public function test_未認証では401を返す(): void
    {
        $response = $this->postJson('/api/quiz/answers', ['answers' => []]);

        $response->assertStatus(401);
    }

    public function test_answersを指定しないと422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();

        $response = $this->actingAsUser($user)->postJson('/api/quiz/answers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('answers');
    }

    public function test_answersが上限を超えると422を返す(): void
    {
        [$user, $word] = $this->単語を持つユーザーを作る();
        $over = config('quiz.max_question_count') + 1;
        $answers = array_fill(0, $over, ['word_id' => $word->id, 'selected_word_id' => $word->id]);

        $response = $this->回答を送る($user, $answers);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('answers');
    }

    public function test_正解で箱が1つ上がりdue_onが新しい箱の間隔分先になる(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 箱 1 の単語に正解すると箱 2（間隔 7 日）へ進む
        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->復習状態を作る($user, $word, 1);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.correct', true);
        $response->assertJsonPath('results.0.box', 2);
        $response->assertJsonPath('results.0.due_on', '2026-08-22');
        $this->assertDatabaseHas('word_reviews', [
            'word_id' => $word->id,
            'box' => 2,
            'correct_count' => 1,
        ]);
    }

    public function test_誤答で箱が0に戻る(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        [$user, $word] = $this->単語を持つユーザーを作る();
        $other = Word::factory()->for($user)->for($word->category)->create();
        $this->復習状態を作る($user, $word, 4);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $other->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.correct', false);
        $response->assertJsonPath('results.0.box', 0);
        // 箱 0 の間隔は 1 日
        $response->assertJsonPath('results.0.due_on', '2026-08-16');
    }

    public function test_selected_word_idがnullなら誤答として扱う(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 時間切れや「わからない」で選ばずに進んだ場合
        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->復習状態を作る($user, $word, 3);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => null],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.correct', false);
        $response->assertJsonPath('results.0.box', 0);
        $this->assertDatabaseHas('word_reviews', [
            'word_id' => $word->id,
            'incorrect_count' => 1,
        ]);
    }

    public function test_未出題の単語でも復習状態が作られて適用される(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // word_reviews に行が無い状態から始める
        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->assertDatabaseCount('word_reviews', 0);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.box', 1);
        $this->assertDatabaseHas('word_reviews', [
            'word_id' => $word->id,
            'user_id' => $user->id,
            'box' => 1,
            'correct_count' => 1,
        ]);
    }

    public function test_箱が既定まで進むとis_learnedがtrueになる(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // learned_box は 3。箱 2 の単語に正解すると 3 に達する
        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->復習状態を作る($user, $word, 2);
        $this->assertFalse((bool) $word->is_learned);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.box', config('quiz.learned_box'));
        $response->assertJsonPath('results.0.is_learned', true);
        $this->assertTrue((bool) $word->fresh()->is_learned);
    }

    public function test_箱が既定に満たなければis_learnedは変わらない(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->復習状態を作る($user, $word, 0);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.is_learned', false);
        $this->assertFalse((bool) $word->fresh()->is_learned);
    }

    public function test_誤答してもis_learnedはfalseに戻らない(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 学習済みの単語を誤答する。箱は 0 に戻るが学習済みフラグは剥がさない
        [$user, $word] = $this->単語を持つユーザーを作る();
        $word->is_learned = true;
        $word->save();
        $this->復習状態を作る($user, $word, 4);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => null],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0.box', 0);
        $response->assertJsonPath('results.0.is_learned', true);
        $this->assertTrue((bool) $word->fresh()->is_learned);
    }

    public function test_他人の単語はskippedに数えられ復習状態も作られない(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        [$me] = $this->単語を持つユーザーを作る();
        [, $othersWord] = $this->単語を持つユーザーを作る();

        $response = $this->回答を送る($me, [
            ['word_id' => $othersWord->id, 'selected_word_id' => $othersWord->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 1, 'correct' => 0, 'skipped' => 1]);
        $response->assertJsonCount(0, 'results');
        // 他人の単語の復習状態を勝手に作らない
        $this->assertDatabaseCount('word_reviews', 0);
    }

    public function test_削除済みの単語が混じっても残りの回答は適用される(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 一括送信の肝。1件消えただけでバッチ全体が落ちると他の学習結果まで巻き添えになる
        [$user, $word] = $this->単語を持つユーザーを作る();
        $消えた単語 = Word::factory()->for($user)->for($word->category)->create();
        $消えたid = $消えた単語->id;
        $消えた単語->delete();

        $response = $this->回答を送る($user, [
            ['word_id' => $消えたid, 'selected_word_id' => $消えたid],
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 2, 'correct' => 1, 'skipped' => 1]);
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.word_id', $word->id);
    }

    public function test_同じword_idが重複していたら最初の1件だけ適用する(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        [$user, $word] = $this->単語を持つユーザーを作る();
        $this->復習状態を作る($user, $word, 1);

        // 1件目は正解、2件目は誤答。適用されるのは1件目だけ
        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
            ['word_id' => $word->id, 'selected_word_id' => null],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 2, 'correct' => 1, 'skipped' => 1]);
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.box', 2);
        $this->assertDatabaseHas('word_reviews', [
            'word_id' => $word->id,
            'box' => 2,
            'correct_count' => 1,
            'incorrect_count' => 0,
        ]);
    }

    public function test_集計はtotalがresultsとskippedの合計になる(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $words = Word::factory()->count(3)->for($user)->for($category)->create();
        [, $othersWord] = $this->単語を持つユーザーを作る();

        $response = $this->回答を送る($user, [
            ['word_id' => $words[0]->id, 'selected_word_id' => $words[0]->id],
            ['word_id' => $words[1]->id, 'selected_word_id' => $words[1]->id],
            ['word_id' => $words[2]->id, 'selected_word_id' => null],
            ['word_id' => $othersWord->id, 'selected_word_id' => $othersWord->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 4, 'correct' => 2, 'skipped' => 1]);
        $response->assertJsonCount(3, 'results');

        $body = $response->json();
        $this->assertSame($body['total'], count($body['results']) + $body['skipped']);
    }

    public function test_結果には単語と意味と箱と期日と学習済みが入る(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        $user = User::factory()->create();
        $word = Word::factory()->for($user)->for(Category::factory()->for($user))->create([
            'word' => 'abandon',
            'meaning' => '見捨てる',
        ]);

        $response = $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('results.0', [
            'word_id' => $word->id,
            'word' => 'abandon',
            'meaning' => '見捨てる',
            'correct' => true,
            'box' => 1,
            'due_on' => '2026-08-18',
            'is_learned' => false,
        ]);
    }
}
