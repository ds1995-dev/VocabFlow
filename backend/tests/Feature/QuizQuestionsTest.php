<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuizQuestionsTest extends TestCase
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
     * 4択が組める最低限の単語を持つユーザーを作る。
     *
     * @return array{0: User, 1: Category}
     */
    private function 単語を持つユーザーを作る(int $wordCount = 12): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Word::factory()->count($wordCount)->for($user)->for($category)->create();

        return [$user, $category];
    }

    /**
     * 指定した期日の復習状態を持つ単語を作る。
     */
    private function 復習状態のある単語を作る(User $user, Category $category, string $dueOn): Word
    {
        $word = Word::factory()->for($user)->for($category)->create();

        WordReview::factory()->for($user)->for($word)->create([
            'box' => 1,
            'due_on' => $dueOn,
        ]);

        return $word;
    }

    public function test_未認証では401を返す(): void
    {
        $response = $this->getJson('/api/quiz/questions?mode=random');

        $response->assertStatus(401);
    }

    public function test_modeを指定しないと422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mode');
    }

    public function test_存在しないmodeは422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=unknown');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mode');
    }

    public function test_categoryモードでcategory_idを省略すると422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=category');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_他人のカテゴリーidは422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();
        [, $otherCategory] = $this->単語を持つユーザーを作る();

        $response = $this->actingAsUser($user)
            ->getJson("/api/quiz/questions?mode=category&category_id={$otherCategory->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_単語が4件未満なら422を返す(): void
    {
        // 4択が原理的に組めないので、出題0件ではなくエラーにする
        [$user] = $this->単語を持つユーザーを作る(3);

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=random');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('words');
    }

    public function test_countを省略すると既定の10問返る(): void
    {
        [$user] = $this->単語を持つユーザーを作る(20);

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=random');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'questions');
        $response->assertJson(['mode' => 'random', 'direction' => 'mixed']);
    }

    public function test_countで出題数を指定できる(): void
    {
        [$user] = $this->単語を持つユーザーを作る(20);

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=random&count=3');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'questions');
    }

    public function test_countが上限を超えると422を返す(): void
    {
        [$user] = $this->単語を持つユーザーを作る();
        $over = config('quiz.max_question_count') + 1;

        $response = $this->actingAsUser($user)->getJson("/api/quiz/questions?mode=random&count={$over}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('count');
    }

    public function test_選択肢は4件で正解が必ず含まれる(): void
    {
        [$user] = $this->単語を持つユーザーを作る(20);

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=random&count=5');

        $response->assertStatus(200);

        foreach ($response->json('questions') as $question) {
            $this->assertCount(config('quiz.choice_count'), $question['choices']);
            $choiceIds = array_column($question['choices'], 'word_id');
            $this->assertContains($question['answer_word_id'], $choiceIds);
            // 同じ単語が2回出てこない
            $this->assertSame($choiceIds, array_values(array_unique($choiceIds)));
        }
    }

    public function test_en_to_jaは英単語を出題し例文を返す(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Word::factory()->count(10)->for($user)->for($category)->create([
            'word' => 'abandon',
            'meaning' => '見捨てる',
            'sentence' => 'He abandoned the plan.',
        ]);

        $response = $this->actingAsUser($user)
            ->getJson('/api/quiz/questions?mode=random&direction=en_to_ja&count=1');

        $response->assertStatus(200);
        $question = $response->json('questions.0');

        $this->assertSame('en_to_ja', $question['direction']);
        $this->assertSame('abandon', $question['prompt']);
        $this->assertSame('He abandoned the plan.', $question['sentence']);
    }

    public function test_ja_to_enは例文を返さない(): void
    {
        // 例文には答えの英単語がそのまま含まれるので、返すと答えが露出する
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Word::factory()->count(10)->for($user)->for($category)->create([
            'word' => 'abandon',
            'meaning' => '見捨てる',
            'sentence' => 'He abandoned the plan.',
        ]);

        $response = $this->actingAsUser($user)
            ->getJson('/api/quiz/questions?mode=random&direction=ja_to_en&count=5');

        $response->assertStatus(200);

        foreach ($response->json('questions') as $question) {
            $this->assertSame('ja_to_en', $question['direction']);
            $this->assertSame('見捨てる', $question['prompt']);
            $this->assertNull($question['sentence']);
        }
    }

    public function test_mixedでも問題ごとの方向はen_to_jaかja_to_enになる(): void
    {
        [$user] = $this->単語を持つユーザーを作る(20);

        $response = $this->actingAsUser($user)
            ->getJson('/api/quiz/questions?mode=random&direction=mixed&count=10');

        $response->assertStatus(200);
        // 封筒には mixed が入るが、1問ごとには解決済みの方向しか現れない
        $response->assertJson(['direction' => 'mixed']);

        foreach ($response->json('questions') as $question) {
            $this->assertContains($question['direction'], ['en_to_ja', 'ja_to_en']);
        }
    }

    public function test_categoryモードは指定カテゴリーの単語だけを出題する(): void
    {
        $user = User::factory()->create();
        $target = Category::factory()->for($user)->create();
        $other = Category::factory()->for($user)->create();

        $targetWords = Word::factory()->count(2)->for($user)->for($target)->create();
        Word::factory()->count(8)->for($user)->for($other)->create();

        $response = $this->actingAsUser($user)
            ->getJson("/api/quiz/questions?mode=category&category_id={$target->id}&count=10");

        $response->assertStatus(200);
        // カテゴリーの単語が4件未満でも、出題語はそこから取り誤答は全単語プールから補う
        $response->assertJsonCount(2, 'questions');

        $askedIds = array_column($response->json('questions'), 'word_id');
        foreach ($askedIds as $id) {
            $this->assertContains($id, $targetWords->pluck('id')->all());
        }
    }

    public function test_reviewモードは期日が来た単語を古い順に返す(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        // 誤答プールを確保するための埋め草
        Word::factory()->count(6)->for($user)->for($category)->create();

        $古い = $this->復習状態のある単語を作る($user, $category, '2026-08-10');
        $今日 = $this->復習状態のある単語を作る($user, $category, '2026-08-15');
        $未来 = $this->復習状態のある単語を作る($user, $category, '2026-08-20');

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=review&count=2');

        $response->assertStatus(200);
        $askedIds = array_column($response->json('questions'), 'word_id');

        // 期日の古い順、未来の期日は含まれない
        $this->assertSame([$古い->id, $今日->id], $askedIds);
        $this->assertNotContains($未来->id, $askedIds);
    }

    public function test_reviewモードは期日が足りなければ未出題の単語で補充する(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 補充しないと、復習モードしか使わないユーザーの新規単語が永久に SRS に入らない
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $期日到来 = $this->復習状態のある単語を作る($user, $category, '2026-08-10');
        $未出題 = Word::factory()->count(5)->for($user)->for($category)->create();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=review&count=4');

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'questions');

        $askedIds = array_column($response->json('questions'), 'word_id');
        $this->assertContains($期日到来->id, $askedIds);
        // 残り3件は未出題から補われている
        $this->assertCount(3, array_intersect($askedIds, $未出題->pluck('id')->all()));
    }

    public function test_reviewモードで対象が0件なら200で空配列を返す(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 全単語が未来の期日 = 今日の復習なし。エラーではなく正常な状態
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        for ($i = 0; $i < 5; $i++) {
            $this->復習状態のある単語を作る($user, $category, '2026-08-20');
        }

        $response = $this->actingAsUser($user)->getJson('/api/quiz/questions?mode=review');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'questions');
    }

    public function test_他人の単語は出題にも選択肢にも混ざらない(): void
    {
        [$me] = $this->単語を持つユーザーを作る(10);
        [$other] = $this->単語を持つユーザーを作る(10);

        $otherWordIds = Word::query()->where('user_id', $other->id)->pluck('id')->all();

        $response = $this->actingAsUser($me)->getJson('/api/quiz/questions?mode=random&count=10');

        $response->assertStatus(200);

        foreach ($response->json('questions') as $question) {
            $this->assertNotContains($question['word_id'], $otherWordIds);
            foreach (array_column($question['choices'], 'word_id') as $choiceId) {
                $this->assertNotContains($choiceId, $otherWordIds);
            }
        }
    }

    public function test_同じ意味の単語は選択肢に重複しない(): void
    {
        // 「見捨てる」が2つ並ぶと答えを選べない問題になってしまう
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Word::factory()->count(10)->for($user)->for($category)->create([
            'meaning' => '見捨てる',
        ]);

        $response = $this->actingAsUser($user)
            ->getJson('/api/quiz/questions?mode=random&direction=en_to_ja&count=5');

        $response->assertStatus(200);

        foreach ($response->json('questions') as $question) {
            $texts = array_column($question['choices'], 'text');
            $this->assertSame($texts, array_values(array_unique($texts)));
            // 意味が全部同じなので、重複を除くと選択肢は正解の1件だけに減る
            $this->assertCount(1, $texts);
        }
    }
}
