<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillLearningActivitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_既存の単語からword_createdが生成される(): void
    {
        // アクティビティ導入前に登録された想定の単語（ファクトリは記録を作らない）
        $me = User::factory()->create();
        $words = Word::factory()->count(3)->for($me)->for(Category::factory()->for($me))->create();

        $this->artisan('study:backfill-activities')->assertExitCode(0);

        // 単語 1 件につき word_created が 1 件生成される
        $this->assertSame(3, LearningActivity::query()->count());
        foreach ($words as $word) {
            $this->assertDatabaseHas('learning_activities', [
                'user_id' => $me->id,
                'word_id' => $word->id,
                'type' => 'word_created',
            ]);
        }
    }

    public function test_2回実行しても重複して生成されない(): void
    {
        // 冪等性の確認
        $me = User::factory()->create();
        Word::factory()->count(2)->for($me)->for(Category::factory()->for($me))->create();

        $this->artisan('study:backfill-activities')->assertExitCode(0);
        $this->artisan('study:backfill-activities')->assertExitCode(0);

        $this->assertSame(2, LearningActivity::query()->count());
    }

    public function test_バックフィルの学習日は日本時間で換算される(): void
    {
        // UTC 2026-08-05 15:30 に作成された単語は日本時間では 2026-08-06
        Carbon::setTestNow(Carbon::parse('2026-08-05 15:30:00', 'UTC'));

        $me = User::factory()->create();
        Word::factory()->for($me)->for(Category::factory()->for($me))->create();

        $this->artisan('study:backfill-activities')->assertExitCode(0);

        // UTC の日付（8/5）ではなく学習日（8/6）で入る
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'studied_on' => '2026-08-06',
        ]);
    }

    public function test_学習済みの単語からword_learnedは生成されない(): void
    {
        // updated_at は編集で上書きされるため「いつ覚えたか」は復元不能。
        // 推測でデータを作らないことを固定する。
        $me = User::factory()->create();
        $word = Word::factory()->for($me)->for(Category::factory()->for($me))->create();
        $word->is_learned = true;
        $word->save();

        $this->artisan('study:backfill-activities')->assertExitCode(0);

        $this->assertDatabaseMissing('learning_activities', [
            'type' => 'word_learned',
        ]);
    }

    public function test_既に記録がある単語は二重に生成されない(): void
    {
        // アクティビティ導入後に登録された単語（API 経由で既に記録済み）
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $created = $this->actingAs($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);
        $created->assertStatus(201);

        $this->artisan('study:backfill-activities')->assertExitCode(0);

        // 登録時の 1 件だけで、バックフィルは何も足さない
        $this->assertSame(1, LearningActivity::query()->count());
    }
}
