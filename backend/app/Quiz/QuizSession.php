<?php

namespace App\Quiz;

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * クイズ1セッション分の回答を適用する。
 *
 * QuizBuilder と対になる書き込み側。出題と同じく「誰かの現在の状態」ではないのでモデルには置かない。
 */
class QuizSession
{
    /**
     * セッションの回答をまとめて適用し、集計と1問ごとの結果を返す。
     *
     * @param  array<int, array{word_id: int, selected_word_id?: int|null}>  $answers
     * @return array<string, mixed>
     */
    public static function submit(User $user, array $answers): array
    {
        // 途中で落ちて中途半端に適用された状態を残さない。
        return DB::transaction(function () use ($user, $answers) {
            $studyDate = LearningActivity::currentStudyDate();
            $applicable = self::withoutDuplicates($answers);
            $words = self::ownedWords($user, $applicable);

            $results = [];

            foreach ($applicable as $answer) {
                $word = $words->get((int) $answer['word_id']);

                // 削除済み・他人の単語。バッチ全体を落とさず、この1件だけ飛ばす。
                if ($word === null) {
                    continue;
                }

                $results[] = self::apply($word, $answer['selected_word_id'] ?? null, $studyDate);
            }

            // 1セッションにつき1件だけ記録する。ストリークは distinct studied_on で数えるので
            // 件数を増やしても伸びない一方、1回答1行にすると書き込みが問題数の分だけ増える。
            // 単語ごとの成績は word_reviews の box / correct_count / incorrect_count に残る。
            if ($results !== []) {
                LearningActivity::record($user, ActivityType::QuizAnswered);
            }

            return [
                'total' => count($answers),
                'correct' => count(array_filter($results, fn (array $result) => $result['correct'])),
                // total = count(results) + skipped が常に成立する。
                'skipped' => count($answers) - count($results),
                'results' => $results,
            ];
        });
    }

    /**
     * 同一バッチ内で重複した word_id を最初の1件だけに絞る。
     *
     * @param  array<int, array<string, mixed>>  $answers
     * @return array<int, array<string, mixed>>
     */
    private static function withoutDuplicates(array $answers): array
    {
        $seen = [];
        $unique = [];

        foreach ($answers as $answer) {
            $wordId = (int) $answer['word_id'];

            if (isset($seen[$wordId])) {
                continue;
            }

            $seen[$wordId] = true;
            $unique[] = $answer;
        }

        return $unique;
    }

    /**
     * 回答対象のうち、自分が持っている単語だけを id 引きできる形で返す。
     *
     * @param  array<int, array<string, mixed>>  $answers
     * @return Collection<int, Word>
     */
    private static function ownedWords(User $user, array $answers)
    {
        $wordIds = array_map(fn (array $answer) => (int) $answer['word_id'], $answers);

        return $user->words()
            ->whereIn('id', $wordIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * 1件の回答を適用して結果行を返す。
     *
     * @return array<string, mixed>
     */
    private static function apply(Word $word, ?int $selectedWordId, string $studyDate): array
    {
        // 出題時の選択肢は word_id がそのまま答えなので、一致だけで判定できる。
        // 未選択（null）は誤答として扱う。
        $isCorrect = $selectedWordId !== null && $selectedWordId === $word->id;

        $review = WordReview::forWord($word);
        $review->applyAnswer($isCorrect, $studyDate);

        self::promoteLearned($word, $review);

        return [
            'word_id' => $word->id,
            'word' => $word->word,
            'meaning' => $word->meaning,
            'correct' => $isCorrect,
            'box' => $review->box,
            'due_on' => $review->due_on->toDateString(),
            // words.is_learned は Word 側に boolean cast が無く tinyint の 0/1 で出てくる。
            // レスポンスでは真偽値として返したいのでここで揃える。
            'is_learned' => (bool) $word->is_learned,
        ];
    }

    /**
     * 箱が既定まで進んだ単語を学習済みにする。
     *
     * 上げるだけで下げない。誤答しても false には戻さないので、手動で付けた学習済みフラグを
     * クイズが勝手に剥がすことはない。ただし箱が既定に達している単語を手動で OFF にしても、
     * 次に正解すれば条件が成立したままなので true に戻る（手動 OFF は次のクイズまで有効）。
     */
    private static function promoteLearned(Word $word, WordReview $review): void
    {
        if ($word->is_learned || $review->box < config('quiz.learned_box')) {
            return;
        }

        // is_learned は $fillable に無いので、toggleLearned と同じく直接代入する。
        $word->is_learned = true;
        $word->save();
    }
}
