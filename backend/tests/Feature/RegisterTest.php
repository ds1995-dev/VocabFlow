<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_正しい情報で登録すると201を返す(): void
    {
        // 有効な入力（password は confirmed のため確認用も送る）
        $response = $this->postJson('/api/register', [
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'User created successfully']);
        // ユーザーが DB に作成されていることを確認
        $this->assertDatabaseHas('users', ['email' => 'taro@example.com']);
    }

    public function test_重複したemailで登録すると422を返す(): void
    {
        // 既に同じ email のユーザーが存在する状態を作る
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'テスト太郎',
            'email' => 'dup@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // unique:users,email 違反で email のバリデーションエラーになる
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_パスワードが8文字未満だと422を返す(): void
    {
        // min:8 違反
        $response = $this->postJson('/api/register', [
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_パスワード確認が一致しないと422を返す(): void
    {
        // confirmed 違反（password_confirmation が一致しない）
        $response = $this->postJson('/api/register', [
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_nameが無いと422を返す(): void
    {
        // name 必須（required）違反
        $response = $this->postJson('/api/register', [
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }
}
