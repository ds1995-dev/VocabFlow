<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BearerTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_不正なトークンでは401を返す(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_認証ヘッダーが無いと401を返す(): void
    {
        // guard を [] にしたのでセッションへのフォールバックは存在しない
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_削除済みトークンでは401を返す(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $user->tokens()->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_有効期限を過ぎたトークンでは401を返す(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // sanctum.expiration（既定 43200 分 = 30 日）を超えた発行日時に巻き戻す
        $user->tokens()->update([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + 1),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_他ユーザーのトークンでは自分の単語しか取得できない(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $alice->words()->create([
            'word' => 'alice-word',
            'meaning' => 'アリスの単語',
            'category_id' => $alice->categories()->create(['name' => 'alice-cat'])->id,
        ]);

        $response = $this->actingAsUser($bob)->getJson('/api/words');

        $response->assertStatus(200);
        // bob には alice の単語が一切見えない
        $this->assertSame([], $response->json());
    }

    public function test_同一テスト内でユーザーを切り替えると切り替え後のユーザーになる(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAsUser($alice)->getJson('/api/user')
            ->assertStatus(200)
            ->assertJson(['id' => $alice->id]);

        // guard が解決済みユーザーをキャッシュして alice のまま返さないことを確認する
        $this->actingAsUser($bob)->getJson('/api/user')
            ->assertStatus(200)
            ->assertJson(['id' => $bob->id]);
    }
}
