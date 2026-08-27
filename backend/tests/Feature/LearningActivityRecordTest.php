<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LearningActivityRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_単語を作成すると学習アクティビティが記録される(): void
    {
        // 自分のカテゴリに単語を 1 件登録する
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $response = $this->actingAsUser($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);

        $response->assertStatus(201);
        // 登録した単語に紐づく word_created が 1 件記録される
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'word_id' => $response->json('id'),
            'type' => 'word_created',
        ]);
    }

    public function test_学習済みに切り替えるとword_learnedが記録される(): void
    {
        // 未学習の単語を学習済みに切り替える
        $me = User::factory()->create();
        $word = Word::factory()->for($me)->for(Category::factory()->for($me))->create();

        $response = $this->actingAsUser($me)->patchJson("/api/words/{$word->id}/toggle-learned");

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'word_id' => $word->id,
            'type' => 'word_learned',
        ]);
    }

    public function test_学習済みを解除するとword_unlearnedが記録される(): void
    {
        // 既に学習済みの単語を解除する（is_learned は $fillable 外なので直接代入する）
        $me = User::factory()->create();
        $word = Word::factory()->for($me)->for(Category::factory()->for($me))->create();
        $word->is_learned = true;
        $word->save();

        $response = $this->actingAsUser($me)->patchJson("/api/words/{$word->id}/toggle-learned");

        $response->assertStatus(200);
        // 解除も記録は残す（ストリークに数えないだけ）
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'word_id' => $word->id,
            'type' => 'word_unlearned',
        ]);
    }

    public function test_単語を編集してもアクティビティは記録されない(): void
    {
        // 誤字修正などの編集は学習行為ではない
        $me = User::factory()->create();
        $category = Category::factory()->for($me)->create();
        $word = Word::factory()->for($me)->for($category)->create();

        $response = $this->actingAsUser($me)->patchJson("/api/words/{$word->id}", [
            'word' => 'updated',
            'meaning' => '更新後の意味',
            'sentence' => 'Updated sentence.',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('learning_activities', [
            'user_id' => $me->id,
        ]);
    }

    public function test_他人の単語のトグルは403となりアクティビティも記録されない(): void
    {
        // 他人が所有する単語
        $owner = User::factory()->create();
        $word = Word::factory()->for($owner)->for(Category::factory()->for($owner))->create();

        $me = User::factory()->create();

        $response = $this->actingAsUser($me)->patchJson("/api/words/{$word->id}/toggle-learned");

        $response->assertStatus(403);
        // 認可で弾かれるので誰の記録も残らない
        $this->assertDatabaseMissing('learning_activities', [
            'word_id' => $word->id,
        ]);
    }

    public function test_単語を削除しても学習アクティビティは残る(): void
    {
        // word_id を nullOnDelete にしている理由の回帰テスト。
        // cascadeOnDelete にすると単語を消した瞬間に過去のストリークが遡って壊れる。
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $created = $this->actingAsUser($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);
        $wordId = $created->json('id');

        $response = $this->actingAsUser($me)->deleteJson("/api/words/{$wordId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('words', ['id' => $wordId]);
        // 単語は消えるが、学習した事実は word_id が null になって残る
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'word_id' => null,
            'type' => 'word_created',
        ]);
    }

    public function test_日本時間で日付が変わった直後に作成した単語は新しい日として記録される(): void
    {
        // UTC 2026-08-05 15:00 は JST では 2026-08-06 00:00。
        // UTC の日付（8/5）ではなく学習日（8/6）で記録されなければならない。
        Carbon::setTestNow(Carbon::parse('2026-08-05 15:00:00', 'UTC'));

        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $response = $this->actingAsUser($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'studied_on' => '2026-08-06',
        ]);
    }

    public function test_日本時間で日付が変わる直前に作成した単語は当日として記録される(): void
    {
        // UTC 2026-08-06 14:59 は JST では 2026-08-06 23:59 でまだ同じ日
        Carbon::setTestNow(Carbon::parse('2026-08-06 14:59:00', 'UTC'));

        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $response = $this->actingAsUser($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $me->id,
            'studied_on' => '2026-08-06',
        ]);
    }
}
