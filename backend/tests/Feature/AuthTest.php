<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SPA フロントエンド由来のステートフルなリクエストとして扱わせ、
        // Sanctum のセッション認証（session ミドルウェア）を有効化する。
        // APP_URL の localhost は sanctum.stateful に含まれる。
        $this->withHeader('Origin', config('app.url'));
    }

    public function test_正しい資格情報でログインすると200を返す(): void
    {
        // ファクトリの既定パスワードは 'password'
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
        // セッション認証が確立されていることを確認
        $this->assertAuthenticated();
    }

    public function test_誤った資格情報でログインすると422を返す(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // 失敗時は errors.email 形式の 422 を返す
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        // 認証されていないこと
        $this->assertGuest();
    }

    public function test_未認証では保護ルートが401を返す(): void
    {
        // ログインせずに認証必須ルートへアクセスすると 401 になる
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_ログイン後は保護ルートに200でアクセスできる(): void
    {
        $user = User::factory()->create();

        // 実際にログインしてセッションを確立する
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        // 確立したセッションで保護ルートへアクセスできる
        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
    }

    public function test_ログアウト後は保護ルートが再び401を返す(): void
    {
        $user = User::factory()->create();

        // ログイン → 保護ルートにアクセス可能
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);

        // ログアウトでセッションを破棄する
        $this->postJson('/api/logout')->assertStatus(200);

        // テストでは共有アプリ内で guard がログイン時のユーザーをキャッシュするため、
        // 後続リクエストで破棄済みセッションを再評価させる（本番は毎リクエスト新規解決）。
        $this->app['auth']->forgetGuards();

        // ログアウト後は再び 401 になる
        $this->getJson('/api/user')->assertStatus(401);
    }
}
