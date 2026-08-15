<?php

namespace App\Http\Requests;

use App\Enums\QuizDirection;
use App\Enums\QuizMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class QuizQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 個別リソースを扱わないので Policy は用意しない。StreakController と同じく、
     * 認証ユーザーのリレーション経由で引くことでスコープを担保している。
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
            // 出題内容がモードで大きく変わるので既定値は置かず、必ず指定させる。
            'mode' => ['required', Rule::enum(QuizMode::class)],
            'direction' => ['nullable', Rule::enum(QuizDirection::class)],
            'count' => ['nullable', 'integer', 'min:1', 'max:'.config('quiz.max_question_count')],
            // required と nullable を1つの配列に混ぜると意図が読めなくなるのでモードで分ける。
            'category_id' => $this->quizMode()?->requiresCategory()
                ? ['required', $this->ownedCategoryRule()]
                : ['nullable', $this->ownedCategoryRule()],
        ];
    }

    /**
     * 出題モード。バリデーション後に呼ぶので null にはならない。
     */
    public function mode(): QuizMode
    {
        return QuizMode::from($this->validated('mode'));
    }

    /**
     * 出題方向。省略時はミックス。
     */
    public function direction(): QuizDirection
    {
        $direction = $this->validated('direction');

        return $direction === null
            ? QuizDirection::Mixed
            : QuizDirection::from($direction);
    }

    /**
     * 出題数。省略時は config の既定値。
     */
    public function questionCount(): int
    {
        return (int) ($this->validated('count') ?? config('quiz.default_question_count'));
    }

    /**
     * ルール組み立て用の出題モード。
     *
     * 不正な mode では null を返すが、その場合は mode 側のルールが 422 にするので
     * ここでは「カテゴリー必須ではない」と見なすだけでよい。
     */
    private function quizMode(): ?QuizMode
    {
        $mode = $this->input('mode');

        return is_string($mode) ? QuizMode::tryFrom($mode) : null;
    }

    /**
     * 自分が持っているカテゴリーであることを要求するルール。
     *
     * 他人のカテゴリー ID を渡されたら 404 ではなく 422 にする（StoreWordRequest と同じ手口）。
     */
    private function ownedCategoryRule(): Exists
    {
        return Rule::exists('categories', 'id')->where('user_id', $this->user()->id);
    }
}
