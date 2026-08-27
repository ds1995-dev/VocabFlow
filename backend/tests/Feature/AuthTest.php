<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_正しい資格情報でログインすると200とトークンを返す(): void
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
        // アクセストークンが払い出される
        $response->assertJsonStructure(['user', 'token']);
        $this->assertNotEmpty($response->json('token'));
        // パスワードや remember_token はレスポンスに含めない
        $response->assertJsonMissingPath('user.password');
        $response->assertJsonMissingPath('user.remember_token');
        // トークンが DB にも保存されている
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'web',
        ]);
    }

    public function test_device_nameを指定するとトークン名に反映される(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iphone',
        ])->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iphone',
        ]);
    }

    public function test_誤ったパスワードでログインすると422を返す(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // 失敗時は errors.email 形式の 422 を返す
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        // トークンは発行されない
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_存在しないメールアドレスでログインすると422を返す(): void
    {
        // 未登録のメールアドレスでも、登録済みかどうかが判別できないよう
        // 誤ったパスワードのときと同じ 422 + errors.email を返す
        $response = $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_未認証では保護ルートが401を返す(): void
    {
        // トークンを付けずに認証必須ルートへアクセスすると 401 になる
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_発行されたトークンで保護ルートにアクセスできる(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200)->json('token');

        // Authorization ヘッダーに Bearer トークンを載せてアクセスする
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson(['id' => $user->id]);
    }

    public function test_ログアウトするとそのトークンが失効する(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200)->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // トークンが DB から削除されている
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_ログアウト後は同じトークンで401を返す(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200)->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // テストでは共有アプリ内で guard が解決済みユーザーをキャッシュするため、
        // 後続リクエストで失効済みトークンを再評価させる（本番は毎リクエスト新規解決）
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_他ユーザーのトークンでは自分の情報しか返らない(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $token = $alice->createToken('web')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson(['id' => $alice->id]);
        $this->assertNotSame($bob->id, $response->json('id'));
    }
}
