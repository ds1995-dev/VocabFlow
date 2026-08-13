<?php

namespace App\Enums;

enum ActivityType: string
{
    case WordCreated = 'word_created';
    case WordLearned = 'word_learned';
    case WordUnlearned = 'word_unlearned';
    // クイズを1セッション解いたときに記録する。1回答ごとではなくセッションごとに1件。
    case QuizAnswered = 'quiz_answered';

    /**
     * ストリークの連続日数に数える種別かどうか。
     */
    public function countsTowardStreak(): bool
    {
        return match ($this) {
            // 学習済みの解除は取り消し操作。数えるとトグルの往復でストリークを稼げてしまう。
            self::WordUnlearned => false,
            default => true,
        };
    }

    /**
     * ストリークに数える種別の値一覧（whereIn 用）。
     *
     * @return array<int, string>
     */
    public static function streakTypes(): array
    {
        $types = array_filter(
            self::cases(),
            fn (self $type) => $type->countsTowardStreak(),
        );

        return array_values(array_map(fn (self $type) => $type->value, $types));
    }
}
