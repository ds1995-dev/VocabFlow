<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        $validated = $request->validated();

        // device_name はトークン名に使うだけでユーザーの属性ではないので取り除く
        $attributes = Arr::except($validated, 'device_name');
        $attributes['password'] = Hash::make($attributes['password']);

        $user = User::create($attributes);

        // 登録後はそのまま使えるようにアクセストークンを発行して返す
        $token = $user->createToken($validated['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }
}
