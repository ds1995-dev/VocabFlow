<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        // 資格情報を照合。既定ガード（web）でセッションを確立する
        if (! Auth::attempt($request->validated())) {
            // 失敗時は 422 + errors.email 形式（フロントの fieldErrors 処理と揃える）
            throw ValidationException::withMessages([
                'email' => ['認証情報が正しくありません'],
            ]);
        }

        // セッション固定攻撃対策でセッション ID を再生成する
        $request->session()->regenerate();

        return response()->json(['user' => Auth::user()]);
    }
}
