<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_単語一覧は自分の単語だけを返す(): void
    {
        // 自分（2件）と他人（3件）の単語を用意する
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();
        Word::factory()->count(2)->for($me)->for($myCategory)->create();

        $other = User::factory()->create();
        $otherCategory = Category::factory()->for($other)->create();
        Word::factory()->count(3)->for($other)->for($otherCategory)->create();

        $response = $this->actingAs($me)->getJson('/api/words');

        $response->assertStatus(200);
        // 自分の 2 件だけが返り、他人の単語は混ざらない
        $response->assertJsonCount(2);
        foreach ($response->json() as $word) {
            $this->assertSame($me->id, $word['user_id']);
        }
    }

    public function test_他人の単語は更新できず403を返す(): void
    {
        // 他人が所有する単語
        $owner = User::factory()->create();
        $word = Word::factory()->for($owner)->for(Category::factory()->for($owner))->create();

        // 更新を試みるユーザー（バリデーションを通すため自分のカテゴリを用意）
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $response = $this->actingAs($me)->patchJson("/api/words/{$word->id}", [
            'word' => 'updated',
            'meaning' => '更新後の意味',
            'sentence' => 'Updated sentence.',
            'category_id' => $myCategory->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_他人の単語は削除できず403を返す(): void
    {
        $owner = User::factory()->create();
        $word = Word::factory()->for($owner)->for(Category::factory()->for($owner))->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->deleteJson("/api/words/{$word->id}");

        $response->assertStatus(403);
        // 削除されていないことを確認
        $this->assertDatabaseHas('words', ['id' => $word->id]);
    }

    public function test_他人の単語は学習状態を切り替えられず403を返す(): void
    {
        $owner = User::factory()->create();
        $word = Word::factory()->for($owner)->for(Category::factory()->for($owner))->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->patchJson("/api/words/{$word->id}/toggle-learned");

        $response->assertStatus(403);
    }

    public function test_他人のカテゴリを指定して単語を作成すると422を返す(): void
    {
        // 他人のカテゴリ
        $other = User::factory()->create();
        $otherCategory = Category::factory()->for($other)->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $otherCategory->id,
        ]);

        // category_id はログインユーザーのカテゴリに限定されるため exists 違反になる
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category_id');
    }

    public function test_単語を作成すると認証ユーザーが所有者になる(): void
    {
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $response = $this->actingAs($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);

        $response->assertStatus(201);
        // レスポンス・DB とも所有者が認証ユーザーになる
        $response->assertJson(['user_id' => $me->id]);
        $this->assertDatabaseHas('words', [
            'word' => 'apple',
            'user_id' => $me->id,
        ]);
    }

    public function test_自分の単語の更新レスポンスにカテゴリが含まれる(): void
    {
        // update で load('category') 忘れが再発しないことの回帰テスト
        $me = User::factory()->create();
        $category = Category::factory()->for($me)->create();
        $word = Word::factory()->for($me)->for($category)->create();

        $response = $this->actingAs($me)->patchJson("/api/words/{$word->id}", [
            'word' => 'updated',
            'meaning' => '更新後の意味',
            'sentence' => 'Updated sentence.',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('category.id', $category->id);
    }
}
