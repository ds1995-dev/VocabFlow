<?php

namespace App\Models;

use App\Enums\ActivityType;
use Carbon\CarbonImmutable;
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
        return CarbonImmutable::now(config('study.timezone'))->toDateString();
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
