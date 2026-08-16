<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuizQuestionRequest;
use App\Http\Requests\StoreQuizAnswersRequest;
use App\Models\LearningActivity;
use App\Models\WordReview;
use App\Quiz\QuizBuilder;
use App\Quiz\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    /**
     * 4択クイズの問題を組み立てて返す。
     *
     * 誤答の選択肢をクライアントに作らせるとユーザーの全単語をフロントへ配る必要が出るため、
     * 組み立ては丸ごとサーバー側に置いている。
     */
    public function questions(QuizQuestionRequest $request)
    {
        $user = $request->user();
        $choiceCount = config('quiz.choice_count');

        // 登録単語が選択肢の数に満たないと4択が原理的に組めない。
        // 出題対象が0件（今日は復習なし）とは別の話なのでこちらはエラーにする。
        if ($user->words()->count() < $choiceCount) {
            throw ValidationException::withMessages([
                'words' => "クイズを始めるには単語が {$choiceCount} 件以上必要です。",
            ]);
        }

        $questions = QuizBuilder::build(
            $user,
            $request->mode(),
            $request->direction(),
            $request->questionCount(),
            $request->validated('category_id'),
        );

        // 復習対象も未出題も0件なら空配列。エラーではなく「今日は復習なし」という正常な状態。
        return response()->json([
            'mode' => $request->mode()->value,
            'direction' => $request->direction()->value,
            'questions' => $questions,
        ]);
    }

    /**
     * セッション分の回答をまとめて適用する。
     *
     * 新しく参照できるリソースを作るわけではないので 201 ではなく 200 を返す。
     */
    public function store(StoreQuizAnswersRequest $request)
    {
        return response()->json(
            QuizSession::submit($request->user(), $request->validated('answers')),
        );
    }

    /**
     * 今日解くべき件数を返す。
     *
     * ストリーク（学習履歴の集計）とは関心事が別なので /api/streak には相乗りさせない。
     * due_count と new_count を分けるのは、画面の「今日の復習 N件」は期日到来分だけを出したい一方、
     * 出題側（復習モード）は未出題も候補にするため。
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'due_count' => WordReview::dueCountFor($user),
            'new_count' => WordReview::newCountFor($user),
            // フロントに日付ライブラリが無いので、学習日基準の「今日」はサーバ側で教える。
            'today' => LearningActivity::currentStudyDate(),
        ]);
    }
}
