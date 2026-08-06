<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningActivity;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    /**
     * 認証ユーザーの学習ストリークを返す。
     *
     * 個別リソースを扱わないので Policy は用意しない。
     * 認証ユーザーのリレーション経由で集計することでスコープを担保している。
     */
    public function show(Request $request)
    {
        return response()->json(LearningActivity::summaryFor($request->user()));
    }
}
