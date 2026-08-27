<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuizSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 学習日を固定するため、日本時間 2026-08-16 12:00（UTC 03:00）を「今日」とする。
     */
    private function 日本時間の今日を2026年8月16日にする(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 03:00:00', 'UTC'));
    }

    /**
     * 指定した期日の復習状態を持つ単語を作る。
     */
    private function 復習状態のある単語を作る(User $user, string $dueOn): Word
    {
        $word = Word::factory()->for($user)->for(Category::factory()->for($user))->create();

        WordReview::factory()->for($user)->for($word)->create([
            'box' => 1,
            'due_on' => $dueOn,
        ]);

        return $word;
    }

    public function test_単語も履歴もないユーザーは件数が0になる(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertExactJson([
            'due_count' => 0,
            'new_count' => 0,
            'today' => '2026-08-16',
        ]);
    }

    public function test_期日が今日以前の単語がdue_countに数えられる(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        $user = User::factory()->create();
        // 期日超過・今日が期日・未来が期日 の3件
        $this->復習状態のある単語を作る($user, '2026-08-14');
        $this->復習状態のある単語を作る($user, '2026-08-16');
        $this->復習状態のある単語を作る($user, '2026-08-17');

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson(['due_count' => 2]);
    }

    public function test_一度も出題していない単語がnew_countに数えられる(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        $user = User::factory()->create();
        Word::factory()->count(3)->for($user)->for(Category::factory()->for($user))->create();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson(['due_count' => 0, 'new_count' => 3]);
    }

    public function test_復習状態のある単語はnew_countに数えない(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        // due_count と new_count で同じ単語を二重に数えない
        $user = User::factory()->create();
        $this->復習状態のある単語を作る($user, '2026-08-16');
        Word::factory()->count(2)->for($user)->for(Category::factory()->for($user))->create();

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson(['due_count' => 1, 'new_count' => 2]);
    }

    public function test_未来が期日の単語はどちらにも数えられない(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        // 出題済みなので new ではなく、期日はまだ来ていないので due でもない
        $user = User::factory()->create();
        $this->復習状態のある単語を作る($user, '2026-08-20');

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson(['due_count' => 0, 'new_count' => 0]);
    }

    public function test_他人の単語や復習状態は数えられない(): void
    {
        $this->日本時間の今日を2026年8月16日にする();

        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->復習状態のある単語を作る($other, '2026-08-14');
        Word::factory()->count(3)->for($other)->for(Category::factory()->for($other))->create();

        $response = $this->actingAsUser($me)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson(['due_count' => 0, 'new_count' => 0]);
    }

    public function test_todayは日本時間の今日を返す(): void
    {
        // UTC では 2026-08-16 15:00 だが、日本時間では既に 8/17 になっている時刻
        Carbon::setTestNow(Carbon::parse('2026-08-16 15:00:00', 'UTC'));

        $user = User::factory()->create();
        // 日本時間の 8/17 が期日なので、UTC 基準で判定していると数え漏らす
        $this->復習状態のある単語を作る($user, '2026-08-17');

        $response = $this->actingAsUser($user)->getJson('/api/quiz/summary');

        $response->assertStatus(200);
        $response->assertJson([
            'due_count' => 1,
            'today' => '2026-08-17',
        ]);
    }
}
