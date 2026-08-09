<?php

namespace App\Models;

use App\Enums\ActivityType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'word_id',
        'type',
        'studied_on',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            // JSON でも "2026-08-06" の形で返す。
            // 'date' だけだと "2026-08-06T00:00:00.000000Z" になり UTC の日時と誤解される。
            'studied_on' => 'date:Y-m-d',
        ];
    }

    /**
     * 学習アクティビティを 1 件記録する。
     *
     * 学習イベントの記録はすべてこのメソッドを通す。学習日の導出と所有者の紐付けを
     * ここに閉じ込めてあるので、将来クイズを実装するときも呼び出し側は
     * 種別と対象の単語を渡すだけでよく、タイムゾーンを意識する必要がない。
     */
    public static function record(User $user, ActivityType $type, ?Word $word = null): self
    {
        return $user->learningActivities()->create([
            'word_id' => $word?->id,
            'type' => $type,
            'studied_on' => self::currentStudyDate(),
        ]);
    }

    /**
     * 学習日の区切りに使うタイムゾーンでの「今日」を Y-m-d 形式で返す。
     *
     * ストリークの日付境界はすべてこのメソッドを通す。タイムゾーンを読む箇所を
     * ここ1つに閉じ込めておくことで、将来ユーザーごとのタイムゾーンに移すときも
     * 変更範囲がこのメソッドだけで済む。
     */
    public static function currentStudyDate(): string
    {
        return self::studyDateFor(CarbonImmutable::now());
    }

    /**
     * 指定した日時が、学習日タイムゾーンではどの日付にあたるかを Y-m-d 形式で返す。
     *
     * 過去の日時を学習日に読み替えるのはバックフィルだけだが、換算の実装を
     * currentStudyDate() と共有するために切り出してある。
     */
    public static function studyDateFor(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)
            ->setTimezone(config('study.timezone'))
            ->toDateString();
    }

    /**
     * ストリークの集計結果を返す。
     *
     * @return array<string, mixed>
     */
    public static function summaryFor(User $user): array
    {
        $dates = self::studiedDatesFor($user);
        $today = self::currentStudyDate();

        return [
            'current_streak' => self::currentStreak($dates, $today),
            'longest_streak' => self::longestStreak($dates),
            'studied_today' => in_array($today, $dates, true),
            'last_studied_on' => $dates[0] ?? null,
            // フロントに日付ライブラリがないので、学習日基準の「今日」はサーバ側で教える
            'today' => $today,
        ];
    }

    /**
     * ストリークに数える学習日を、新しい順の Y-m-d 配列で返す。
     *
     * @return array<int, string>
     */
    public static function studiedDatesFor(User $user): array
    {
        return $user->learningActivities()
            ->whereIn('type', ActivityType::streakTypes())
            ->distinct()
            ->orderByDesc('studied_on')
            ->pluck('studied_on')
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->all();
    }

    /**
     * 現在の連続学習日数。
     *
     * 「今日 または 昨日」を終端とする連続日数を数える。今日まだ学習していなくても
     * 昨日まで続いていればストリークは生きている。最終学習が 2 日以上前なら 0。
     *
     * @param  array<int, string>  $dates  新しい順の学習日
     */
    private static function currentStreak(array $dates, string $today): int
    {
        if ($dates === []) {
            return 0;
        }

        $yesterday = CarbonImmutable::parse($today)->subDay()->toDateString();

        // 最終学習が今日でも昨日でもなければ、ストリークは途切れている
        if ($dates[0] !== $today && $dates[0] !== $yesterday) {
            return 0;
        }

        $streak = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $expected = CarbonImmutable::parse($dates[$i - 1])->subDay()->toDateString();

            if ($dates[$i] !== $expected) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /**
     * 過去最長の連続学習日数。
     *
     * @param  array<int, string>  $dates  新しい順の学習日
     */
    private static function longestStreak(array $dates): int
    {
        if ($dates === []) {
            return 0;
        }

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $expected = CarbonImmutable::parse($dates[$i - 1])->subDay()->toDateString();

            $current = $dates[$i] === $expected ? $current + 1 : 1;
            $longest = max($longest, $current);
        }

        return $longest;
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
