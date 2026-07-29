<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_未認証では単語一覧が401を返す(): void
    {
        // 認証必須ルートに未ログインでアクセスすると 401 になる
        $response = $this->getJson('/api/words');

        $response->assertStatus(401);
    }

    public function test_認証済みでは単語一覧が200を返す(): void
    {
        // ログイン済みユーザーとしてアクセスすると 200 でデータを取得できる
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/words');

        $response->assertStatus(200);
    }

    public function test_未認証ではユーザー取得が401を返す(): void
    {
        // /api/user も認証必須なので未ログインでは 401 になる
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_認証済みでは現在のユーザーを返す(): void
    {
        // ログイン済みなら自身のユーザー情報が返る
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
