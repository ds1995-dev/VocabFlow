<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_カテゴリ一覧は自分のカテゴリだけを返す(): void
    {
        // 自分（2件）と他人（3件）のカテゴリを用意する
        $me = User::factory()->create();
        Category::factory()->count(2)->for($me)->create();

        $other = User::factory()->create();
        Category::factory()->count(3)->for($other)->create();

        $response = $this->actingAs($me)->getJson('/api/categories');

        $response->assertStatus(200);
        // 自分の 2 件だけが返り、他人のカテゴリは混ざらない
        $response->assertJsonCount(2);
        foreach ($response->json() as $category) {
            $this->assertSame($me->id, $category['user_id']);
        }
    }

    public function test_他人のカテゴリは閲覧できず403を返す(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->getJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    public function test_他人のカテゴリは更新できず403を返す(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->patchJson("/api/categories/{$category->id}", [
            'name' => '更新後の名前',
        ]);

        $response->assertStatus(403);
    }

    public function test_他人のカテゴリは削除できず403を返す(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        $me = User::factory()->create();

        $response = $this->actingAs($me)->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
        // 削除されていないことを確認
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_カテゴリを作成すると認証ユーザーが所有者になる(): void
    {
        $me = User::factory()->create();

        $response = $this->actingAs($me)->postJson('/api/categories', [
            'name' => '英単語',
        ]);

        $response->assertStatus(200);
        // レスポンス・DB とも所有者が認証ユーザーになる
        $response->assertJson(['user_id' => $me->id]);
        $this->assertDatabaseHas('categories', [
            'name' => '英単語',
            'user_id' => $me->id,
        ]);
    }

    public function test_カテゴリを削除すると配下の単語も連鎖削除される(): void
    {
        // 自分のカテゴリと、それに紐づく単語
        $me = User::factory()->create();
        $category = Category::factory()->for($me)->create();
        $words = Word::factory()->count(2)->for($me)->for($category)->create();

        $response = $this->actingAs($me)->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        // カテゴリ削除に伴い配下の単語も cascade 削除される
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        foreach ($words as $word) {
            $this->assertDatabaseMissing('words', ['id' => $word->id]);
        }
    }
}
