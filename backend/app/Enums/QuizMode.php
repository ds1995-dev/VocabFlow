<?php

namespace App\Enums;

enum QuizMode: string
{
    case Category = 'category';
    case Random = 'random';
    case Review = 'review';

    /**
     * 出題対象のカテゴリー指定が必須の種別かどうか。
     */
    public function requiresCategory(): bool
    {
        return $this === self::Category;
    }
}
