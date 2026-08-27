<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // ユーザーが存在しない場合も同じエラーを返し、メールアドレスの登録有無が
        // 判別できないようにする
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            // 失敗時は 422 + errors.email 形式（フロントの fieldErrors 処理と揃える）
            throw ValidationException::withMessages([
                'email' => ['認証情報が正しくありません'],
            ]);
        }

        // 端末ごとにトークンを発行する。Web からは device_name 未指定で 'web' になる
        $token = $user->createToken($validated['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        // このリクエストで使われたトークンだけを失効させる（他端末は維持する）。
        // テスト等で TransientToken が入ることがあるため実体があるときだけ削除する。
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'ログアウトしました']);
    }
}
