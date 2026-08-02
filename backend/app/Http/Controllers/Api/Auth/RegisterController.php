<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // 登録後はそのままログイン状態にする（既定ガード web でセッションを確立）
        Auth::login($user);

        // セッション固定攻撃対策でセッション ID を再生成する
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }
}
