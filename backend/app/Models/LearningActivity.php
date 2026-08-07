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
