<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Category;
use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuizStreakTest extends TestCase
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
     * 回答を送る。
     */
    private function 回答を送る(User $user, array $answers)
    {
        return $this->actingAsUser($user)->postJson('/api/quiz/answers', ['answers' => $answers]);
    }

    public function test_何問回答してもquiz_answeredは1件だけ記録される(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 1回答1行にすると書き込みが問題数の分だけ増えるが、ストリークは distinct studied_on で
        // 数えるので伸び方は変わらない。単語ごとの成績は word_reviews に残る。
        $user = User::factory()->create();
        $words = Word::factory()->count(10)->for($user)->for(Category::factory()->for($user))->create();

        $answers = $words->map(fn (Word $word) => [
            'word_id' => $word->id,
            'selected_word_id' => $word->id,
        ])->all();

        $response = $this->回答を送る($user, $answers);

        $response->assertStatus(200);
        $this->assertSame(1, LearningActivity::query()
            ->where('user_id', $user->id)
            ->where('type', ActivityType::QuizAnswered)
            ->count());
    }

    public function test_quiz_answeredのword_idはnullになる(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        $user = User::factory()->create();
        $word = Word::factory()->for($user)->for(Category::factory()->for($user))->create();

        $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        // セッション単位の記録なので特定の単語には紐づけない
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $user->id,
            'type' => ActivityType::QuizAnswered->value,
            'word_id' => null,
            'studied_on' => '2026-08-15',
        ]);
    }

    public function test_有効な回答が0件なら記録しない(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 全部が他人の単語で skipped されたケース
        $me = User::factory()->create();
        $other = User::factory()->create();
        $othersWord = Word::factory()->for($other)->for(Category::factory()->for($other))->create();

        $response = $this->回答を送る($me, [
            ['word_id' => $othersWord->id, 'selected_word_id' => $othersWord->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 1, 'skipped' => 1]);
        $this->assertDatabaseCount('learning_activities', 0);
    }

    public function test_クイズに回答するとストリークが伸びる(): void
    {
        $this->日本時間の今日を2026年8月15日にする();

        // 昨日まで学習していたユーザーが今日クイズを解くとストリークが 2 日になる
        $user = User::factory()->create();
        $word = Word::factory()->for($user)->for(Category::factory()->for($user))->create();

        LearningActivity::factory()->for($user)->create([
            'word_id' => null,
            'type' => ActivityType::WordCreated,
            'studied_on' => '2026-08-14',
        ]);

        $this->回答を送る($user, [
            ['word_id' => $word->id, 'selected_word_id' => $word->id],
        ]);

        $response = $this->actingAsUser($user)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJson([
            'current_streak' => 2,
            'studied_today' => true,
            'last_studied_on' => '2026-08-15',
        ]);
    }
}
