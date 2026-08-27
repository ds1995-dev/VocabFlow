<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 指定ユーザーのアクセストークンを発行し、以降のリクエストに Bearer で付与する。
     *
     * Sanctum::actingAs() は TransientToken を差し込むだけで personal_access_tokens の
     * 照合を通らないため、本番と同じ経路を通すよう実トークンを発行している。
     */
    protected function actingAsUser(User $user, string $deviceName = 'test'): static
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        // 同一テスト内でユーザーを切り替えたとき、解決済み guard がキャッシュした
        // 前のユーザーを返してしまうため破棄する（本番は毎リクエスト新規解決）
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
