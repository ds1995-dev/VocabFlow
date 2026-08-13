<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum QuizDirection: string
{
    case EnToJa = 'en_to_ja';
    case JaToEn = 'ja_to_en';
    // セッション開始時の指定でのみ使う。1問ごとの方向としては現れない。
    case Mixed = 'mixed';

    /**
     * 1問分の出題方向に解決する。
     *
     * Mixed のときだけ問題ごとにランダムへ振り分ける。出題側は常にこのメソッドを
     * 通すので、questions[].direction に Mixed が漏れることはない。
     */
    public function forQuestion(): self
    {
        return $this === self::Mixed
            ? Arr::random([self::EnToJa, self::JaToEn])
            : $this;
    }

    /**
     * 問題文に例文を添えてよい方向かどうか。
     *
     * 日本語→英語では例文に答えの英単語がそのまま含まれるため添えられない。
     */
    public function allowsSentence(): bool
    {
        return $this === self::EnToJa;
    }
}
