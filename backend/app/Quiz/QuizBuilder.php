<?php

namespace App\Quiz;

use App\Enums\QuizDirection;
use App\Enums\QuizMode;
use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Collection;

/**
 * 4択クイズの問題を組み立てる。
 *
 * 出題の組み立ては「誰かの現在の状態」ではないのでモデルには置かない。Word に置くと
 * 単語がクイズの知識を持ってしまうため、目的で切った名前空間をここに新設している。
 */
class QuizBuilder
{
    /**
     * 出題する問題の一覧を返す。
     *
     * mode / direction を含む外側の封筒はコントローラが組む。ここが返すのは questions だけ。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build(
        User $user,
        QuizMode $mode,
        QuizDirection $direction,
        int $count,
        ?int $categoryId = null,
    ): array {
        $candidates = self::candidatesFor($user, $mode, $count, $categoryId);

        if ($candidates->isEmpty()) {
            return [];
        }

        // 誤答の選択肢はユーザー自身の全単語から作る。全問で使い回すので取得は1回だけ。
        $pool = $user->words()->get();

        return $candidates
            ->map(fn (Word $word) => self::questionFor($word, $direction->forQuestion(), $pool))
            ->all();
    }

    /**
     * モードごとの出題候補。
     *
     * @return Collection<int, Word>
     */
    private static function candidatesFor(User $user, QuizMode $mode, int $count, ?int $categoryId): Collection
    {
        return match ($mode) {
            QuizMode::Category => $user->words()
                ->where('category_id', $categoryId)
                ->inRandomOrder()
                ->limit($count)
                ->get(),
            QuizMode::Random => $user->words()
                ->inRandomOrder()
                ->limit($count)
                ->get(),
            QuizMode::Review => self::reviewCandidatesFor($user, $count),
        };
    }

    /**
     * 復習モードの出題候補。期日が来た単語を古い順に取り、足りなければ未出題の単語で補う。
     *
     * 未出題を混ぜるのは、混ぜないと復習モードしか使わないユーザーの新しく登録した単語が
     * 永久に SRS に入らず「今日の復習 0 件」のまま詰んでしまうため。
     *
     * @return Collection<int, Word>
     */
    private static function reviewCandidatesFor(User $user, int $count): Collection
    {
        // words 側ではなく word_reviews 側から引くのは due_on で並べ替えるため。
        // word_reviews の index(['user_id', 'due_on']) がそのまま効く経路になる。
        $due = $user->wordReviews()
            ->where('due_on', '<=', LearningActivity::currentStudyDate())
            ->orderBy('due_on')
            ->with('word')
            ->limit($count)
            ->get()
            ->pluck('word')
            ->filter()
            ->values();

        $shortage = $count - $due->count();

        if ($shortage <= 0) {
            return $due;
        }

        // 「行が無い = 未出題」。WordReview::newCountFor() と同じ述語で引く。
        $unseen = $user->words()
            ->whereDoesntHave('review')
            ->inRandomOrder()
            ->limit($shortage)
            ->get();

        return $due->concat($unseen);
    }

    /**
     * 1 問分を組み立てる。
     *
     * @param  Collection<int, Word>  $pool  誤答選択肢の候補
     * @return array<string, mixed>
     */
    private static function questionFor(Word $word, QuizDirection $direction, Collection $pool): array
    {
        $answerText = self::choiceText($word, $direction);

        return [
            'word_id' => $word->id,
            'direction' => $direction->value,
            'prompt' => self::promptText($word, $direction),
            // 日本語→英語では例文に答えの英単語がそのまま含まれるので返さない。
            'sentence' => $direction->allowsSentence() ? $word->sentence : null,
            'choices' => self::choicesFor($word, $direction, $pool, $answerText),
            'answer_word_id' => $word->id,
        ];
    }

    /**
     * 正解を混ぜてシャッフルした選択肢。
     *
     * @param  Collection<int, Word>  $pool
     * @return array<int, array<string, mixed>>
     */
    private static function choicesFor(Word $word, QuizDirection $direction, Collection $pool, string $answerText): array
    {
        $distractors = $pool
            ->reject(fn (Word $candidate) => $candidate->id === $word->id)
            // 正解と同じ表示テキストは誤答にならない。「見捨てる」が2つ並ぶと答えられない。
            ->reject(fn (Word $candidate) => self::choiceText($candidate, $direction) === $answerText)
            ->unique(fn (Word $candidate) => self::choiceText($candidate, $direction))
            ->shuffle()
            ->take(config('quiz.choice_count') - 1);

        return $distractors
            ->push($word)
            ->shuffle()
            ->map(fn (Word $candidate) => [
                'word_id' => $candidate->id,
                'text' => self::choiceText($candidate, $direction),
            ])
            ->values()
            ->all();
    }

    /**
     * 問題文。英語→日本語なら英単語、日本語→英語なら意味を出す。
     */
    private static function promptText(Word $word, QuizDirection $direction): string
    {
        return $direction === QuizDirection::JaToEn ? $word->meaning : $word->word;
    }

    /**
     * 選択肢の表示テキスト。問題文と逆側を出す。
     */
    private static function choiceText(Word $word, QuizDirection $direction): string
    {
        return $direction === QuizDirection::JaToEn ? $word->word : $word->meaning;
    }
}
