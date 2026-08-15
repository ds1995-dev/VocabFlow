<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizAnswersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 個別リソースを扱わないので Policy は用意しない。単語の所有権は QuizSession::submit() 内で
     * フィルタし、弾いた件数を skipped として返す。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:'.config('quiz.max_question_count')],
            // word_id にあえて Rule::exists を掛けない。ここで弾くと、セッション中に単語を
            // 1件消しただけでバッチ全体が 422 になり、他の回答の学習結果まで巻き添えで消える。
            // 所有権は submit() 内でフィルタし、弾いた分は skipped で返す。
            'answers.*.word_id' => ['required', 'integer'],
            // 時間切れや「わからない」で選ばずに進む操作を許す。null は誤答として適用する。
            // correct のような真偽値はクライアントから受け取らない（正誤はサーバーが判定する）。
            'answers.*.selected_word_id' => ['nullable', 'integer'],
        ];
    }
}
