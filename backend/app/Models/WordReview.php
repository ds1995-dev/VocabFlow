<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordReview extends Model
{
    use HasFactory;

    /**
     * user_id と word_id は所有者と対象を決める鍵なので $fillable に入れない。
     * words.is_learned と同じ扱いで、必ず呼び出し側が直接代入する。
     */
    protected $fillable = [
        'box',
        'due_on',
        'last_reviewed_on',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // learning_activities.studied_on と同じ理由で日付だけの形で返す。
            // 'date' だけだと "2026-08-12T00:00:00.000000Z" になり UTC の日時と誤解される。
            'due_on' => 'date:Y-m-d',
            'last_reviewed_on' => 'date:Y-m-d',
        ];
    }

    /**
     * 単語の復習状態を返す。まだ一度も出題していない単語ならここで作る。
     *
     * 行を遅延生成するのは、単語登録時に作る方式だと Observer かバックフィルが必要になるうえ、
     * 未出題の単語に box 0 を書き込むのは「まだ何も分かっていない」を状態として捏造するのに
     * 近いため。行が無い＝未出題、という素直な表現にしてある。
     */
    public static function forWord(Word $word): self
    {
        $review = static::query()->firstWhere('word_id', $word->id);

        if ($review !== null) {
            return $review;
        }

        $review = new self([
            'box' => 0,
            'due_on' => LearningActivity::currentStudyDate(),
        ]);

        // 所有者はリクエストからではなく必ず単語から引き継ぐ。
        $review->user_id = $word->user_id;
        $review->word_id = $word->id;
        // DB の default(0) 任せにすると戻り値のモデル側が null のまま残り、
        // 直後の applyAnswer() が null のインクリメントに頼ることになる。
        $review->correct_count = 0;
        $review->incorrect_count = 0;
        $review->save();

        return $review;
    }

    /**
     * 回答を1件適用して保存する。
     *
     * 正解なら1つ上の箱へ（最上位で頭打ち）、誤答なら箱 0 へ戻す。
     * 次の期日は「解いた学習日 + 新しい箱の間隔」で決まる。
     *
     * @param  string  $studyDate  解いた学習日（Y-m-d）
     */
    public function applyAnswer(bool $isCorrect, string $studyDate): void
    {
        $this->box = $isCorrect
            ? min($this->box + 1, self::maxBox())
            : 0;

        $this->due_on = CarbonImmutable::parse($studyDate)
            ->addDays(self::intervalFor($this->box))
            ->toDateString();
        $this->last_reviewed_on = $studyDate;

        if ($isCorrect) {
            $this->correct_count++;
        } else {
            $this->incorrect_count++;
        }

        $this->save();
    }

    /**
     * 箱に対応する復習間隔（日数）。
     */
    public static function intervalFor(int $box): int
    {
        $intervals = config('quiz.intervals');

        return $intervals[min(max($box, 0), self::maxBox())];
    }

    /**
     * 一番上の箱の番号。
     */
    public static function maxBox(): int
    {
        return count(config('quiz.intervals')) - 1;
    }

    /**
     * 復習の期日が来ている単語の数。
     */
    public static function dueCountFor(User $user): int
    {
        return $user->wordReviews()
            ->where('due_on', '<=', LearningActivity::currentStudyDate())
            ->count();
    }

    /**
     * 一度も出題していない単語の数。
     */
    public static function newCountFor(User $user): int
    {
        return $user->words()->whereDoesntHave('review')->count();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function word()
    {
        return $this->belongsTo(Word::class);
    }
}
