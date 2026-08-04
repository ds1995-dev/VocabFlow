<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Word;

class WordPolicy
{
    /**
     * 単語を閲覧できるのは所有者のみ。
     */
    public function view(User $user, Word $word): bool
    {
        return $user->id === $word->user_id;
    }

    /**
     * 単語を更新できるのは所有者のみ（学習状態の切り替えも含む）。
     */
    public function update(User $user, Word $word): bool
    {
        return $user->id === $word->user_id;
    }

    /**
     * 単語を削除できるのは所有者のみ。
     */
    public function delete(User $user, Word $word): bool
    {
        return $user->id === $word->user_id;
    }
}
